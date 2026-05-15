# Architecture

A quick mental model of the moving pieces.

## Components

```
┌──────────────────────────────────────────────────────────┐
│  Your app (controllers, jobs, blade, middleware)         │
└─────────────────────────┬────────────────────────────────┘
                          │
                ┌─────────▼──────────────┐
                │ FeatureFlag (facade)   │  Optional sugar
                └─────────┬──────────────┘
                          │ resolves
                          ▼
                ┌───────────────────────┐
                │  FeatureFlagManager   │  Public API (final readonly)
                └─────────┬─────────────┘
                          │ uses
                          ▼
                ┌───────────────────────┐
                │  FeatureFlagRepository│  Contract (interface)
                └─────────┬─────────────┘
                          │ default impl
                          ▼
                ┌─────────────────────────────┐
                │  EloquentFeatureFlagRepo    │  Reads/writes via Eloquent
                └─────────┬───────────────────┘
                          │
                          ▼
                ┌───────────────────────┐
                │  FeatureFlag model    │  Eloquent model (config-swappable)
                └─────────┬─────────────┘
                          │
                          ▼
                ┌───────────────────────┐
                │   feature_flags table │  DB
                └───────────────────────┘

   Request flow for scope-aware checks:

┌──────────┐   Request    ┌─────────────────────┐   scope id   ┌─────────────────────┐
│ Browser  │ ───────────▶ │ FeatureScopeResolver│ ───────────▶ │ Manager.isEnabled() │
└──────────┘              └─────────────────────┘              └─────────────────────┘

```

The **scope id** is an opaque string. The library has no opinion about what it represents. Could be a team id, region, cohort, user id, anything. See [scopes.md](scopes.md).

## Files

| Path | Role |
|------|------|
| `src/Manager/FeatureFlagManager.php` | Public API used by your app code |
| `src/Facades/FeatureFlag.php` | Static-call sugar over the manager |
| `src/Contracts/FeatureFlagRepository.php` | Storage contract. Implement to swap backend |
| `src/Contracts/FeatureScopeResolver.php` | Per-request scope contract |
| `src/Contracts/FeatureKey.php` | Type-safe key contract for enums |
| `src/Repository/EloquentFeatureFlagRepository.php` | Default Eloquent-backed repo |
| `src/Resolvers/NullScopeResolver.php` | Default scope resolver (returns null) |
| `src/Resolvers/UserTenantScopeResolver.php` | Example resolver (opt-in) |
| `src/Models/FeatureFlag.php` | Default Eloquent model |
| `src/DTO/FeatureTransfer.php` | Read-only DTO for repository returns |
| `src/Http/Middleware/EnsureFeatureIsActive.php` | Route guard |
| `src/Http/Controllers/FeatureFlagController.php` | Admin actions |
| `src/FeatureFlagsServiceProvider.php` | Wiring (binds, routes, publishes, middleware alias) |
| `config/feature-flags.php` | All knobs |
| `database/migrations/*` | Schema |
| `resources/views/admin/index.blade.php` | Admin Blade |
| `routes/admin.php` | Admin route definitions |

## Design principles

### Repository pattern
Controllers and the manager never touch Eloquent directly. All DB access goes through `FeatureFlagRepository`. Swap implementations to back the flags with Redis, a remote service, or in-memory state.

### DTOs for boundaries
Repository writes/reads return `FeatureTransfer`, not Eloquent models. Keeps the package boundary clean and the public API safe to expose to API responses.

### Contract-first
Three small interfaces (`FeatureFlagRepository`, `FeatureScopeResolver`, `FeatureKey`) are the only types your app must care about. Concrete classes are easy to replace.

### Container-driven config
`FeatureFlagsServiceProvider` reads config-defined classes through the container, so resolvers and repository implementations can have constructor dependencies.

### Scope resolution rule
- Scope row beats global row for the same key.
- Missing row → `false` (closed by default).
- Time window narrows further but does not override the scope precedence rule.

## Sequence: `isEnabled('foo', $scopeId)`

1. App calls `FeatureFlagManager::isEnabled('foo', 'some-scope')`.
2. Manager forwards to `FeatureFlagRepository::isEnabled('foo', 'some-scope')`.
3. Repository builds a query:
   - `WHERE key = 'foo'`
   - `AND (scope_id = 'some-scope' OR scope_id IS NULL)`
   - `AND (enabled_from IS NULL OR enabled_from <= now())`
   - `AND (enabled_until IS NULL OR enabled_until >= now())`
   - `ORDER BY scope_id IS NULL ASC` (scoped row first)
   - `LIMIT 1`
4. Returns the first row's `value`, or `false` if no row matched.

## Service provider lifecycle

`register()`:
- merges config
- binds `FeatureFlagRepository` → `EloquentFeatureFlagRepository`
- binds `FeatureScopeResolver` → class from config (default `NullScopeResolver`)
- binds `FeatureFlagManager` as singleton

`boot()`:
- loads migrations + views from package paths
- registers middleware alias (default `feature.enabled`)
- conditionally loads admin routes
- registers publish tags: `feature-flags` (all-in-one), `feature-flags-config`, `feature-flags-migrations`, `feature-flags-views`, `feature-flags-routes`

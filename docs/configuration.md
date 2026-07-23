# Configuration

After `php artisan vendor:publish --tag=feature-flags-config`, edit `config/feature-flags.php`.

```php
return [
    'table' => 'feature_flags',
    'model' => Chemaclass\FeatureFlags\Models\FeatureFlag::class,

    'scope' => [
        'column'   => 'scope_id',
        'resolver' => Chemaclass\FeatureFlags\Resolvers\NullScopeResolver::class,
    ],

    'admin' => [
        'enabled'    => true,
        'prefix'     => 'admin/feature-flags',
        'middleware' => ['web', 'auth'],
        'route_name' => 'feature-flags.',
    ],

    'middleware_alias' => 'feature.enabled',
];
```

## Keys

### `table`
DB table name. Change before running the migration if you want a custom name.

### `model`
Eloquent model class. Swap with your own subclass to add observers, casts, or relations. Must extend `Chemaclass\FeatureFlags\Models\FeatureFlag` or be schema-compatible.

### `scope.column`
Column used for scope filtering. Defaults to `scope_id`. Only change if you also customize the migration.

### `scope.resolver`
Class implementing `FeatureScopeResolver`. Resolves the current request's scope id, a free-form string that means whatever your app wants it to mean (team id, organization id, region code, A/B cohort, anything). Built-ins:

| Class | Behavior |
|-------|----------|
| `NullScopeResolver` (default) | Always returns `null`. Global-only mode |
| `UserTenantScopeResolver` | Example resolver that reads `$user->tenant->id` then falls back to `$user->tenant_id`. Opt-in. |

See [scopes.md](scopes.md) for writing your own.

### `admin.enabled`
Set `false` to skip registering admin routes (e.g. you publish your own).

### `admin.prefix`
URL prefix for admin routes. Default: `admin/feature-flags`.

### `admin.middleware`
Middleware applied to admin routes. Default `['web', 'auth']`. Add `'can:manage-flags'` or similar gates here.

### `admin.route_name`
Name prefix for admin routes. Default `feature-flags.`. Used by `route('feature-flags.toggle', $id)` etc.

### `middleware_alias`
Short alias for `EnsureFeatureIsActive`. Default `feature.enabled`. Use as `->middleware('feature.enabled:my-key')`.

### `cache`
Evaluation cache. Every flag check is **memoized per request** regardless of this setting, so
repeated checks in one request never re-query.

```php
'cache' => [
    'store' => env('FEATURE_FLAGS_CACHE_STORE'), // null = memoization only
    'ttl' => 60,                                 // seconds
    'prefix' => 'feature-flags',
],
```

Set `store` to any cache store name from `config/cache.php` (`redis`, `file`, …) to also cache
evaluations **across requests**. Writes bump a namespace version, so a single flag change
invalidates every cached evaluation instantly — no per-key enumeration, works on any driver.
Leave `store` as `null` to keep memoization-only behavior (no cross-request cache).

### `realtime`
Real-time cache invalidation across nodes. When enabled, every flag write broadcasts a
`FlagsChanged` event and a listener bumps each node's cache namespace version, so a change
propagates instantly instead of waiting for the `cache.ttl`.

```php
'realtime' => [
    'enabled' => env('FEATURE_FLAGS_REALTIME_ENABLED', false),
    'connection' => env('FEATURE_FLAGS_REALTIME_CONNECTION'),
    'channel' => 'feature-flags',
],
```

Only relevant with a `cache.store` configured and a broadcaster set up. Off by default —
no broadcasting. Invalidation is idempotent (version bumps only go up), so echoed broadcasts
are harmless.

## Env-driven config

Wire to `.env` if you want per-environment toggles:

```php
'admin' => [
    'enabled' => env('FEATURE_FLAGS_ADMIN_ENABLED', true),
    'middleware' => array_filter(['web', 'auth', env('FEATURE_FLAGS_ADMIN_GATE')]),
],
```

## Next steps

- [Define and check flags](usage.md)
- [Custom scope resolvers](scopes.md)
- [Admin UI](admin-ui.md)

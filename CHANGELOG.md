# Changelog

[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) · [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **JSON HTTP API** — opt-in `GET|POST {prefix}/evaluate` endpoint that evaluates flags (with
  scope, context, variants) through the manager, so frontends and other services can read flags.
  Configurable prefix + middleware; off by default.
- **JavaScript client** — a zero-dependency browser client (`feature-flags-js` publish tag)
  wrapping the API: `createFeatureFlags().load()` / `.isEnabled()` / `.variant()`.

## [1.0.0] - 2026-07-24

### Added

- **Targeting rules engine** — per-flag attribute rules evaluated against a caller-supplied
  context (`eq, neq, in, not_in, gt, gte, lt, lte, contains, starts_with, ends_with`); a
  matching rule overrides the boolean value.
- **Percentage rollout** — deterministic `rollout_percentage` (0–100); stable ~X% of scopes.
- **Multivariate variants** — weighted `variants` + per-variant `variant_payloads`; `variant()`
  returns the deterministically selected `VariantResult`.
- **Per-environment values** — `environment` column; a row can scope to one environment, null
  applies to all.
- **Prerequisites & kill-switch** — a flag can require other flags (recursive, cycle-safe); a
  `kill_switch` config forces keys off before any query.
- **Batch evaluation** — `allEnabled([...keys], $scope, $context)` resolves many flags in one query.
- **Caching** — per-request memoization (always on) and an optional cross-request cache store with
  namespace-version invalidation, via a `CachingFeatureFlagRepository` decorator.
- **Real-time invalidation** — optional `FlagsChanged` broadcast + listener that busts every node's
  cache instantly instead of waiting for the TTL.
- **Blade** — `@feature` / `@unlessfeature` directives resolving the current scope.
- **Events** — `FlagToggled` (always) and `FlagEvaluated` (opt-in, off by default).
- **Audit log** — opt-in `feature_flag_audits` table + listener recording every toggle with actor,
  and a per-flag history panel in the admin UI.
- **Artisan** — `flag:list`, `flag:create`, `flag:toggle`, `flag:delete`, `flag:stale`
  (flag-debt detection), `flag:generate` (typed enum codegen), `flag:sync` (config-as-code / GitOps).
- **Laravel Pennant bridge** — optional driver so `Feature::active()` resolves through this package
  (kept as a `suggest`, zero impact when Pennant is absent).
- **Contracts** — `AuditActorResolver`; repository gains `allEnabled`, `variant`, `staleKeys`,
  `distinctKeys`, `allFlags`.
- Additive, reversible migrations add `environment`, `rollout_percentage`, `rules`, `prerequisites`,
  `variants`, `variant_payloads`, plus the audits table.
- **100% test coverage**, enforced in CI (`pest --coverage --min=100`).
- Live demo / feature tour on GitHub Pages, linked from the README.

### Changed

- Evaluation now layers **kill-switch → targeting rules → percentage rollout → boolean**, scoped by
  `(scope_id, environment)` precedence. Existing boolean flags behave exactly as before.
- `isEnabled` / `allEnabled` gained an optional `array $context = []` argument (threaded through the
  manager and facade); all existing call sites are unaffected.
- Flags unique constraint widened from `(key, scope_id)` to `(key, scope_id, environment)`.
- README rewritten for progressive onboarding; installation schema table refreshed.
- Tooling: upgraded to Pest 4; bumped larastan/phpstan/pint; `composer stan` now passes
  `--memory-limit=512M` to match CI.

## [0.1.0] - 2026-05-15

### Added

- `FeatureFlagManager` + `FeatureFlag` facade.
- `FeatureFlagRepository` contract with Eloquent default.
- `FeatureScopeResolver` contract; `NullScopeResolver` (default) and `UserTenantScopeResolver` (example).
- `FeatureKey` enum contract for type-safe keys.
- `EnsureFeatureIsActive` middleware + `feature.enabled` alias.
- Admin UI at `/admin/feature-flags`: grouped by key, inline edits, toggle switches, dark mode.
- `feature_flags` migration: ULID, `(key, scope_id)` unique, time windows, dev marker.
- Publish tags: `feature-flags` (all), `-config`, `-migrations`, `-views`, `-routes`.
- Docker demo (`make up`) + Testbench workbench.
- Docs under `docs/`; CI matrix on PHP 8.3/8.4 × Laravel 11/12.

[Unreleased]: https://github.com/Chemaclass/laravel-feature-flags/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Chemaclass/laravel-feature-flags/releases/tag/v1.0.0
[0.1.0]: https://github.com/Chemaclass/laravel-feature-flags/releases/tag/v0.1.0

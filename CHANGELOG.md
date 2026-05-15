# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-05-15

### Added

- Initial package scaffold for agnostic Laravel feature flags.
- `FeatureFlagManager` public API with `isEnabled`, `all`, `findById`,
  `findByKeyAndScope`, `create`, `updateOrCreate`, `update`, `delete`,
  `toggleValue`, `toggleDevByKey`.
- `FeatureFlagRepository` contract with default Eloquent implementation
  (`EloquentFeatureFlagRepository`).
- `FeatureScopeResolver` contract with two bundled resolvers:
  `NullScopeResolver` (default, global-only mode) and
  `UserTenantScopeResolver` (opt-in example).
- `FeatureKey` enum contract for type-safe flag keys.
- `EnsureFeatureIsActive` middleware plus configurable alias
  (`feature.enabled` by default) and `::using()` helper.
- Admin UI at `/admin/feature-flags` with grouping by key, real toggle
  switches, inline hint and time-window editing, scope override forms,
  dark mode toggle, and color-hashed scope badges.
- `feature_flags` table migration: ULID id, key, scope_id, value, hint,
  is_dev, enabled_from, enabled_until, unique `(key, scope_id)`.
- Publish tags: `feature-flags-config`, `feature-flags-migrations`,
  `feature-flags-views`, `feature-flags-routes`.
- Testbench workbench demo app with seeded flags and Docker setup
  (`docker compose up` / `make up`).
- Full docs under `docs/` covering installation, configuration, usage,
  scopes, middleware, admin UI, extending, testing, recipes,
  architecture.

[Unreleased]: https://github.com/Chemaclass/laravel-feature-flags/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Chemaclass/laravel-feature-flags/releases/tag/v0.1.0

# Changelog

[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) · [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/Chemaclass/laravel-feature-flags/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Chemaclass/laravel-feature-flags/releases/tag/v0.1.0
[0.1.0]: https://github.com/Chemaclass/laravel-feature-flags/releases/tag/v0.1.0

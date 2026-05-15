# laravel-feature-flags

[![CI](https://github.com/Chemaclass/laravel-feature-flags/actions/workflows/ci.yml/badge.svg)](https://github.com/Chemaclass/laravel-feature-flags/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/chemaclass/laravel-feature-flags.svg)](https://packagist.org/packages/chemaclass/laravel-feature-flags)
[![Packagist Downloads](https://img.shields.io/packagist/dt/chemaclass/laravel-feature-flags.svg)](https://packagist.org/packages/chemaclass/laravel-feature-flags)
[![PHP](https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white)](composer.json)
[![Laravel](https://img.shields.io/badge/Laravel-11.x%20%7C%2012.x-FF2D20?logo=laravel&logoColor=white)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Agnostic, DB-backed feature flags for Laravel. Global toggles, per-scope overrides, time windows, dev markers, middleware guard, and a zero-build Blade admin page with dark mode.

> Drop it in any Laravel app. No vendor lock-in, no opinionated stack, no assumptions about your domain.

## Highlights

- **Agnostic**: a scope is whatever you want (team, org, region, cohort, user) via a `FeatureScopeResolver` contract
- **Per-scope overrides**: scoped row beats global; null scope = global default
- **Time windows**: `enabled_from` / `enabled_until` gating
- **Dev marker**: `is_dev` flag for filtering in non-prod environments
- **Type-safe keys**: enum-based `FeatureKey` contract
- **Facade + middleware**: `FeatureFlag::isEnabled(...)` and the `feature.enabled` route guard
- **Admin UI**: published Blade page with toggle switches, inline edits, dark mode, scope grouping
- **Repository pattern**: swap `EloquentFeatureFlagRepository` for any backend

## Install

```bash
composer require chemaclass/laravel-feature-flags
php artisan vendor:publish --tag=feature-flags
php artisan migrate
```

The single `feature-flags` tag publishes the config, the migration, the admin view, and the admin routes file. See [docs/installation.md](docs/installation.md) for per-tag installs.

## 30-second usage

```php
use Chemaclass\FeatureFlags\Contracts\FeatureKey;
use Chemaclass\FeatureFlags\Facades\FeatureFlag;

enum AppFeature: string implements FeatureKey
{
    case NewDashboard = 'new-dashboard';

    public function key(): string { return $this->value; }
}

// Global check
if (FeatureFlag::isEnabled(AppFeature::NewDashboard)) {
    // gated code
}

// Scoped check. $scopeId is whatever string your app decides on
// (team id, organization id, region code, cohort name, etc.)
if (FeatureFlag::isEnabled(AppFeature::NewDashboard, $scopeId)) {
    // gated code
}
```

Full API on the facade: `isEnabled`, `all`, `create`, `update`, `updateOrCreate`, `delete`, `toggleValue`, `toggleDevByKey`, `findById`, `findByKeyAndScope`. See [docs/usage.md](docs/usage.md).

Route guard:

```php
use Chemaclass\FeatureFlags\Http\Middleware\EnsureFeatureIsActive;

Route::get('/dashboard', DashboardController::class)
    ->middleware(EnsureFeatureIsActive::using(AppFeature::NewDashboard));

// or via alias
Route::get('/dashboard', DashboardController::class)
    ->middleware('feature.enabled:new-dashboard');
```

## Admin UI

Visit `/admin/feature-flags` (configurable, gated by `web`+`auth` by default). Features:

- Flags grouped by key, global row tinted, scope overrides nested
- Real sliding toggle switches, inline hint and time-window editing
- Per-row and per-key dev marker toggles
- Add scope override / new flag forms
- Dark mode toggle, default follows OS
- Color-hashed scope badges

See [docs/admin-ui.md](docs/admin-ui.md).

## Documentation

| Topic | Link |
|-------|------|
| Installation & setup | [docs/installation.md](docs/installation.md) |
| Configuration reference | [docs/configuration.md](docs/configuration.md) |
| Defining & checking flags | [docs/usage.md](docs/usage.md) |
| Scope resolvers | [docs/scopes.md](docs/scopes.md) |
| Middleware guard | [docs/middleware.md](docs/middleware.md) |
| Admin UI | [docs/admin-ui.md](docs/admin-ui.md) |
| Custom repository / storage | [docs/extending.md](docs/extending.md) |
| Testing | [docs/testing.md](docs/testing.md) |
| Recipes & patterns | [docs/recipes.md](docs/recipes.md) |
| Architecture overview | [docs/architecture.md](docs/architecture.md) |

## Publish tags

| Tag | What it publishes |
|-----|-------------------|
| `feature-flags` | All of the below in one shot |
| `feature-flags-config` | `config/feature-flags.php` |
| `feature-flags-migrations` | DB migration |
| `feature-flags-views` | Blade admin view |
| `feature-flags-routes` | Admin routes file |

## Requirements

- PHP `^8.3`
- Laravel `^11.0 || ^12.0`

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for local dev setup, the Docker demo app, the test suites, formatting, and the `release.sh` flow.

## License

MIT.

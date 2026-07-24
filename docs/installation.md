# Installation

## Requirements

- PHP `^8.3`
- Laravel `^11.0 || ^12.0`
- A relational DB supported by Laravel (MySQL, PostgreSQL, SQLite, SQL Server)

## Install via Composer

```bash
composer require chemaclass/laravel-feature-flags
```

The package auto-registers `FeatureFlagsServiceProvider` via `extra.laravel.providers` in `composer.json`. No manual provider wiring needed.

## Publish everything (recommended)

```bash
php artisan vendor:publish --tag=feature-flags
php artisan migrate
```

This single tag publishes the config, the migration, the admin view, and the admin routes file in one shot.

## Or publish piece by piece

```bash
php artisan vendor:publish --tag=feature-flags-config       # config/feature-flags.php
php artisan vendor:publish --tag=feature-flags-migrations   # DB migration
php artisan vendor:publish --tag=feature-flags-views        # Blade admin view (optional)
php artisan vendor:publish --tag=feature-flags-routes       # Admin routes (optional)
php artisan vendor:publish --tag=feature-flags-js           # JS API client (optional)
php artisan migrate
```

Creates the `feature_flags` table (configurable via `feature-flags.table`).

Schema:

| Column | Type | Notes |
|--------|------|-------|
| `id` | ULID | Primary key |
| `key` | string | Flag key, indexed |
| `scope_id` | string nullable | Free-form scope id (any string); `null` = global |
| `environment` | string nullable | Scope to one env; `null` = all envs |
| `value` | bool | On/off |
| `rollout_percentage` | tinyint nullable | Deterministic % rollout (0–100) |
| `rules` | json nullable | Attribute targeting rules |
| `prerequisites` | json nullable | Keys that must all be enabled |
| `variants` | json nullable | Weighted A/B/n variants |
| `variant_payloads` | json nullable | Per-variant payloads |
| `hint` | string nullable | Free-text label |
| `is_dev` | bool | Dev marker |
| `enabled_from` | timestamp nullable | Window start |
| `enabled_until` | timestamp nullable | Window end |
| `created_at`/`updated_at` | timestamps | - |

Unique constraint: `(key, scope_id, environment)`.

The base table ships the core columns; the richer columns are added by additive migrations
bundled with the package, so `php artisan migrate` brings everything up to date. An optional
`feature_flag_audits` table is created for the [audit log](extending.md).

## Verify

```bash
php artisan route:list | grep feature-flags
```

You should see the admin routes: `index`, `store`, `update`, `toggle`, `toggle-dev-row`, `toggle-dev`, `destroy`.

## Disabling the bundled routes

If you want to wire the admin routes yourself (custom layout, controller, or auth), publish the routes file (`--tag=feature-flags-routes`) and then in config:

```php
'admin' => ['enabled' => false],
```

Include the published file from your own `routes/web.php`:

```php
require base_path('routes/feature-flags.php');
```

## Next steps

- [Configuration reference](configuration.md)
- [Define and check flags](usage.md)

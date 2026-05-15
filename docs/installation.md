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

## Publish config

```bash
php artisan vendor:publish --tag=feature-flags-config
```

Produces `config/feature-flags.php`. See [configuration.md](configuration.md) for the full reference.

## Publish migrations

```bash
php artisan vendor:publish --tag=feature-flags-migrations
php artisan migrate
```

Creates the `feature_flags` table (configurable via `feature-flags.table`).

Schema:

| Column | Type | Notes |
|--------|------|-------|
| `id` | ULID | Primary key |
| `key` | string | Flag key, indexed |
| `scope_id` | string nullable | Free-form scope id (any string); `null` = global |
| `value` | bool | On/off |
| `hint` | string nullable | Free-text label |
| `is_dev` | bool | Dev marker |
| `enabled_from` | timestamp nullable | Window start |
| `enabled_until` | timestamp nullable | Window end |
| `created_at`/`updated_at` | timestamps | - |

Unique constraint: `(key, scope_id)`.

## Optional: publish views

```bash
php artisan vendor:publish --tag=feature-flags-views
```

Customizes the admin Blade at `resources/views/vendor/feature-flags/admin/index.blade.php`.

## Optional: publish routes

```bash
php artisan vendor:publish --tag=feature-flags-routes
```

Copies the admin routes to `routes/feature-flags.php` if you want to manage them yourself. Then disable the bundled admin route loader in config:

```php
'admin' => [
    'enabled' => false,
    // ...
],
```

## Verify

```bash
php artisan route:list | grep feature-flags
```

You should see `GET /admin/feature-flags` (default prefix) and the toggle/store/destroy routes.

## Next steps

- [Configuration reference](configuration.md)
- [Define and check flags](usage.md)

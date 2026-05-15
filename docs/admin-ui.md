# Admin UI

The package ships a single-file Blade admin page at `/admin/feature-flags` (configurable). Tailwind CDN, vanilla JS, no build step.

## Features

- Flags grouped by key. The global row (`scope_id = null`) is tinted; scope overrides nest beneath.
- Real sliding **toggle switch** for value, debounce-saved.
- Per-row **DEV** badge that flips `is_dev` on click.
- Per-key **DEV** badge that flips `is_dev` on every row of the key.
- Inline **hint** input, debounce-saved.
- Inline **enabled_from** / **enabled_until** datetime pickers, saved on change.
- **+ Add scope override** form per group.
- **+ New flag** form covering every column.
- **Delete row** with confirm.
- **Dark mode** toggle in the navbar; default follows `prefers-color-scheme`; choice persisted to `localStorage`.
- Color-hashed scope badges so the same scope id renders the same color across the page.
- Animated toast on every save.

## Access

Default URL: `/admin/feature-flags`
Default middleware: `['web', 'auth']`

Change in `config/feature-flags.php`:

```php
'admin' => [
    'enabled'    => true,
    'prefix'     => 'ops/flags',                       // custom URL prefix
    'middleware' => ['web', 'auth', 'can:manage-flags'], // gate with a policy
    'route_name' => 'feature-flags.',
],
```

## Routes registered

| Verb | Path | Route name | Purpose |
|------|------|------------|---------|
| GET | `/` | `feature-flags.index` | Render the grouped admin page |
| POST | `/` | `feature-flags.store` | Create or upsert by `(key, scope_id)` |
| PATCH | `/{id}` | `feature-flags.update` | Partial update (hint, dates, value, is_dev) |
| POST | `/{id}/toggle` | `feature-flags.toggle` | Flip the row's `value` |
| POST | `/{id}/toggle-dev` | `feature-flags.toggle-dev-row` | Flip a single row's `is_dev` |
| POST | `/toggle-dev/{key}` | `feature-flags.toggle-dev` | Flip `is_dev` on every row of `key` |
| DELETE | `/{id}` | `feature-flags.destroy` | Remove the row |

All paths are prefixed with `config('feature-flags.admin.prefix')` (default `admin/feature-flags`).

The mutation endpoints sit in Laravel's `web` middleware group, so they require a CSRF token. The bundled JS reads it from `<meta name="csrf-token">`. For programmatic access from elsewhere, use the `FeatureFlag` facade or the `FeatureFlagManager` (see [usage.md](usage.md)).

## Customize the view

```bash
php artisan vendor:publish --tag=feature-flags-views
```

Edit `resources/views/vendor/feature-flags/admin/index.blade.php`. Variables in the view:

- `$entriesByKey`: `Collection<string, Collection<FeatureFlag>>` grouped by `key`
- `$total`: int total row count

## Disable the bundled routes

To wire the routes yourself (custom layout, controller, or auth flow):

```bash
php artisan vendor:publish --tag=feature-flags-routes
```

Then:

```php
'admin' => ['enabled' => false],
```

And include the published file from your own `routes/web.php`:

```php
require base_path('routes/feature-flags.php');
```

## Securing the UI

The admin page can change behavior in production. Gate it:

```php
Gate::define('manage-flags', fn (User $user) => $user->is_admin === true);
```

```php
'middleware' => ['web', 'auth', 'can:manage-flags'],
```

## API-only deployments

If you don't need the Blade UI, set `admin.enabled = false`. Manager, facade, and middleware still work.

## Next steps

- [Custom storage / repository](extending.md)
- [Testing](testing.md)

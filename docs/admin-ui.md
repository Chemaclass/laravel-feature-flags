# Admin UI

The package ships a minimal Blade admin page at `/admin/feature-flags` (configurable) showing all flags, scope rows, toggle buttons, and delete actions.

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

## What you can do from the UI

| Action | Route name |
|--------|------------|
| List flags grouped by key | `feature-flags.index` |
| Create or update a flag | `feature-flags.store` |
| Toggle a row's value | `feature-flags.toggle` |
| Toggle `is_dev` for a key | `feature-flags.toggle-dev` |
| Delete a row | `feature-flags.destroy` |

## Customize the view

Publish:

```bash
php artisan vendor:publish --tag=feature-flags-views
```

Edit `resources/views/vendor/feature-flags/admin/index.blade.php`. The view uses the Tailwind CDN by default; swap for your stack as needed.

Variables available in the view:
- `$entriesByKey` — `Collection<string, Collection<FeatureFlag>>` grouped by `key`
- `$total` — int row count

## Disable the bundled routes

If you want to wire admin routes yourself (custom layout, custom controller actions), publish the routes file:

```bash
php artisan vendor:publish --tag=feature-flags-routes
```

Then in config:

```php
'admin' => [
    'enabled' => false,
],
```

Include the routes file from your own `routes/web.php`:

```php
require base_path('routes/feature-flags.php');
```

## Securing the UI

The admin page can change behavior in production — gate it:

```php
// app/Providers/AuthServiceProvider.php
Gate::define('manage-flags', fn (User $user) => $user->is_admin === true);
```

Then in config:

```php
'middleware' => ['web', 'auth', 'can:manage-flags'],
```

## API-only deployments

If you don't need the Blade UI:

```php
'admin' => [
    'enabled' => false,
    // ...
],
```

You can still use the manager, middleware, and controller endpoints programmatically.

## Next steps

- [Custom storage / repository](extending.md)
- [Testing](testing.md)

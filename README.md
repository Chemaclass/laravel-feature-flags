# laravel-feature-flags

Agnostic feature flag management for Laravel apps. DB-backed toggles with optional per-scope overrides, time windows, dev markers, and a plain Blade admin page.

## Install

```bash
composer require chemaclass/laravel-feature-flags
php artisan vendor:publish --tag=feature-flags-config
php artisan vendor:publish --tag=feature-flags-migrations
php artisan migrate
```

## Define your flags

```php
use Chemaclass\FeatureFlags\Contracts\FeatureKey;

enum AppFeature: string implements FeatureKey
{
    case NewDashboard = 'new-dashboard';

    public function key(): string { return $this->value; }
}
```

## Check a flag

```php
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;

if (app(FeatureFlagManager::class)->isEnabled(AppFeature::NewDashboard, $tenantId)) {
    // ...
}
```

## Middleware

```php
use Chemaclass\FeatureFlags\Http\Middleware\EnsureFeatureIsActive;

Route::get('/x', fn () => '...')
    ->middleware(EnsureFeatureIsActive::using(AppFeature::NewDashboard));
```

Or via alias:

```php
Route::get('/x', fn () => '...')->middleware('feature.enabled:new-dashboard');
```

## Scope resolver

Default reads `$user->tenant->id` or `$user->tenant_id`. Override in config:

```php
'scope' => [
    'column'   => 'scope_id',
    'resolver' => App\Support\MyScopeResolver::class,
],
```

Implement `Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver`.

## Admin UI

Visit `/admin/feature-flags` (protected by `web`+`auth` by default). Change prefix/middleware in `config/feature-flags.php`.

## Publish tags

- `feature-flags-config`
- `feature-flags-migrations`
- `feature-flags-views`
- `feature-flags-routes`

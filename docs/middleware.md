# Middleware Guard

`EnsureFeatureIsActive` blocks requests when a flag is off for the current scope.

## Usage

### Static helper (type-safe)

```php
use Chemaclass\FeatureFlags\Http\Middleware\EnsureFeatureIsActive;

Route::get('/dashboard', DashboardController::class)
    ->middleware(EnsureFeatureIsActive::using(AppFeature::NewDashboard));
```

The `::using()` helper accepts either a `FeatureKey` enum case or a raw string and emits the correct `Class:key` middleware notation.

### Via alias

The service provider registers an alias (default `feature.enabled`):

```php
Route::get('/dashboard', DashboardController::class)
    ->middleware('feature.enabled:new-dashboard');
```

Alias is configurable in `config/feature-flags.php` → `middleware_alias`.

### Route groups

```php
Route::middleware('feature.enabled:beta-billing')->group(function () {
    Route::get('/billing/beta', BetaBillingController::class);
    Route::post('/billing/beta/setup', BetaBillingSetupController::class);
});
```

## What it does

1. Resolves the current scope via the configured `FeatureScopeResolver`.
2. Calls `FeatureFlagManager::isEnabled($feature, $scopeId)`.
3. If disabled → returns `400 Bad Request` JSON: `{"message": "Feature disabled"}`.
4. If enabled → forwards to the next middleware.

## Customizing the disabled response

The default 400+JSON response is intentionally simple. If you want a 404, redirect, or HTML page, write a thin wrapper:

```php
namespace App\Http\Middleware;

use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class FeatureOr404
{
    public function __construct(
        private FeatureFlagManager $manager,
        private FeatureScopeResolver $scopeResolver,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->manager->isEnabled($feature, $this->scopeResolver->resolve($request))) {
            abort(404);
        }

        return $next($request);
    }
}
```

Register as an alias in `bootstrap/app.php` (Laravel 11+) and use as `feature.or404:my-key`.

## Per-controller checks instead

Sometimes a middleware is too coarse. You want to gate one method, not a route. Use the facade:

```php
use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Facades\FeatureFlag;

final class DashboardController
{
    public function __construct(
        private readonly FeatureScopeResolver $scope,
    ) {}

    public function show(Request $request)
    {
        $scopeId = $this->scope->resolve($request);

        return FeatureFlag::isEnabled(AppFeature::NewDashboard, $scopeId)
            ? view('dashboard.new')
            : view('dashboard.legacy');
    }
}
```

## Next steps

- [Admin UI](admin-ui.md)
- [Recipes](recipes.md)

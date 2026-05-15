# Recipes

Common patterns assembled from the building blocks. The `$scopeId` placeholder below is any string your app decides on — see [scopes.md](scopes.md).

## Gradual rollout: enable for one scope, then more

```php
$manager->updateOrCreate(['key' => 'new-checkout', 'scope_id' => null],       ['value' => false]);
$manager->updateOrCreate(['key' => 'new-checkout', 'scope_id' => 'group-A'],  ['value' => true]);
// later
$manager->updateOrCreate(['key' => 'new-checkout', 'scope_id' => 'group-B'],  ['value' => true]);
// when ready globally
$manager->updateOrCreate(['key' => 'new-checkout', 'scope_id' => null], ['value' => true]);
```

## Kill switch

```php
// Global off; delete scope overrides so nothing can re-enable it accidentally
$manager->updateOrCreate(['key' => 'risky-feature', 'scope_id' => null], ['value' => false]);
DB::table('feature_flags')->where('key', 'risky-feature')->whereNotNull('scope_id')->delete();
```

Or set `enabled_until = now()` to expire immediately.

## Scheduled rollout

```php
$manager->updateOrCreate(
    ['key' => 'holiday-banner', 'scope_id' => null],
    [
        'value' => true,
        'enabled_from'  => '2026-12-20 00:00',
        'enabled_until' => '2026-12-27 23:59',
    ],
);
```

## Blade conditional

Add a directive in a service provider:

```php
use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Support\Facades\Blade;

public function boot(): void
{
    Blade::if('feature', function (string $key) {
        $manager = app(FeatureFlagManager::class);
        $scope = app(FeatureScopeResolver::class)->resolve(request());

        return $manager->isEnabled($key, $scope);
    });
}
```

Then in templates:

```blade
@feature('new-dashboard')
    <x-dashboard.new />
@else
    <x-dashboard.legacy />
@endfeature
```

## Inertia share

Share flag state with the frontend via Inertia middleware:

```php
final class ShareFeatureFlags
{
    public function __construct(
        private readonly FeatureFlagManager $manager,
        private readonly FeatureScopeResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        Inertia::share('flags', $this->manager->all($this->resolver->resolve($request)));

        return $next($request);
    }
}
```

Frontend:

```js
const flags = usePage().props.flags;
if (flags['new-dashboard']) { /* ... */ }
```

## Console command guard

```php
final class HeavyExportCommand extends Command
{
    public function handle(FeatureFlagManager $manager): int
    {
        if (! $manager->isEnabled('async-exports')) {
            $this->warn('Disabled by feature flag.');
            return self::SUCCESS;
        }

        // ...
    }
}
```

## Queue job guard

```php
final class GenerateReport implements ShouldQueue
{
    public function __construct(public string $scopeId) {}

    public function handle(FeatureFlagManager $manager): void
    {
        if (! $manager->isEnabled('async-exports', $this->scopeId)) {
            $this->release(60);
            return;
        }
        // ...
    }
}
```

## A/B cohort

Use a stable hash as scope id:

```php
$cohort = 'cohort-'.(crc32((string) $user->id) % 2 === 0 ? 'A' : 'B');
if ($manager->isEnabled('experiment-x', $cohort)) {
    // variant
}
```

## Audit log via model events

Subclass the model and observe writes:

```php
class FeatureFlag extends BaseFeatureFlag
{
    protected static function booted(): void
    {
        static::saved(function (self $flag): void {
            Log::info('flag_changed', [
                'key'      => $flag->key,
                'scope_id' => $flag->scope_id,
                'value'    => $flag->value,
                'by'       => auth()->id(),
            ]);
        });
    }
}
```

Wire it via `feature-flags.model` config.

## Caching reads

Wrap the repository — see [extending.md](extending.md#caching-the-eloquent-repository).

## Route-aware scope picking

Different routes need different scope strategies? Compose a route-aware resolver:

```php
final class RouteAwareScopeResolver implements FeatureScopeResolver
{
    public function resolve(Request $request): ?string
    {
        return match (true) {
            $request->is('api/admin/*') => $request->header('X-Scope-Id'),
            $request->is('api/*')       => $request->user()?->organization_id,
            default                     => null,
        };
    }
}
```

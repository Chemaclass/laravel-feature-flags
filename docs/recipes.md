# Recipes

Common patterns assembled from the building blocks. The `$scopeId` placeholder below is any string your app decides on. See [scopes.md](scopes.md).

All examples use the `FeatureFlag` facade. Substitute `app(FeatureFlagManager::class)` if you prefer container injection.

```php
use Chemaclass\FeatureFlags\Facades\FeatureFlag;
```

## Gradual rollout

```php
FeatureFlag::updateOrCreate(['key' => 'new-checkout', 'scope_id' => null],      ['value' => false]);
FeatureFlag::updateOrCreate(['key' => 'new-checkout', 'scope_id' => 'group-A'], ['value' => true]);
// later
FeatureFlag::updateOrCreate(['key' => 'new-checkout', 'scope_id' => 'group-B'], ['value' => true]);
// when ready globally
FeatureFlag::updateOrCreate(['key' => 'new-checkout', 'scope_id' => null], ['value' => true]);
```

## Kill switch

```php
FeatureFlag::updateOrCreate(['key' => 'risky-feature', 'scope_id' => null], ['value' => false]);
DB::table('feature_flags')->where('key', 'risky-feature')->whereNotNull('scope_id')->delete();
```

Or expire immediately:

```php
$row = FeatureFlag::findByKeyAndScope('risky-feature', null);
FeatureFlag::update($row->id, ['enabled_until' => now()]);
```

## Scheduled rollout

```php
FeatureFlag::updateOrCreate(
    ['key' => 'holiday-banner', 'scope_id' => null],
    [
        'value'         => true,
        'enabled_from'  => '2026-12-20 00:00',
        'enabled_until' => '2026-12-27 23:59',
    ],
);
```

## Blade directive

Register once in a service provider:

```php
use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Facades\FeatureFlag;
use Illuminate\Support\Facades\Blade;

public function boot(): void
{
    Blade::if('feature', function (string $key) {
        $scope = app(FeatureScopeResolver::class)->resolve(request());

        return FeatureFlag::isEnabled($key, $scope);
    });
}
```

Use in templates:

```blade
@feature('new-dashboard')
    <x-dashboard.new />
@else
    <x-dashboard.legacy />
@endfeature
```

## Inertia share

```php
use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Facades\FeatureFlag;

final class ShareFeatureFlags
{
    public function __construct(
        private readonly FeatureScopeResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        Inertia::share('flags', FeatureFlag::all($this->resolver->resolve($request)));

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
    public function handle(): int
    {
        if (! FeatureFlag::isEnabled('async-exports')) {
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

    public function handle(): void
    {
        if (! FeatureFlag::isEnabled('async-exports', $this->scopeId)) {
            $this->release(60);
            return;
        }
        // ...
    }
}
```

## A/B cohort with a stable hash

```php
$cohort = 'cohort-'.(crc32((string) $user->id) % 2 === 0 ? 'A' : 'B');
if (FeatureFlag::isEnabled('experiment-x', $cohort)) {
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

Wrap the repository. See [extending.md](extending.md#caching-the-eloquent-repository).

## Route-aware scope picking

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

## Bulk cleanup of stale flags

```php
use Chemaclass\FeatureFlags\Models\FeatureFlag as Model;

Model::query()
    ->where('updated_at', '<', now()->subMonths(6))
    ->each(fn ($row) => FeatureFlag::delete($row->id));
```

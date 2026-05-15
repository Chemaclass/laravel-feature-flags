# Extending

Two extension points let you swap the package internals: the **model** and the **repository**.

## Customize the Eloquent model

Extend `FeatureFlag` to add casts, observers, scopes, or relations:

```php
namespace App\Models;

use Chemaclass\FeatureFlags\Models\FeatureFlag as BaseFeatureFlag;

class FeatureFlag extends BaseFeatureFlag
{
    protected $casts = [
        'value'         => 'boolean',
        'is_dev'        => 'boolean',
        'enabled_from'  => 'datetime',
        'enabled_until' => 'datetime',
        'meta'          => 'array', // your extra column
    ];

    protected static function booted(): void
    {
        static::saved(fn (self $flag) => activity()->log("Flag {$flag->key} updated"));
    }
}
```

Register in config:

```php
'model' => App\Models\FeatureFlag::class,
```

The default repository reads `config('feature-flags.model')` so it'll instantiate your subclass.

## Replace the repository entirely

Implement `FeatureFlagRepository` to swap the storage layer (Redis, KV store, remote service, in-memory cache, etc.).

```php
namespace App\Support;

use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\DTO\FeatureTransfer;

final class RedisFeatureFlagRepository implements FeatureFlagRepository
{
    public function isEnabled(string $key, ?string $scopeId = null): bool
    {
        // your logic
    }

    public function listForScope(?string $scopeId): array { /* ... */ }
    public function findById(string $id): ?FeatureTransfer { /* ... */ }
    public function findByKeyAndScope(string $key, ?string $scopeId): ?FeatureTransfer { /* ... */ }
    public function create(array $data): FeatureTransfer { /* ... */ }
    public function updateOrCreate(array $attributes, array $values): FeatureTransfer { /* ... */ }
    public function toggleValue(string $id): bool { /* ... */ }
    public function toggleDevByKey(string $key): bool { /* ... */ }
}
```

Bind in `AppServiceProvider`:

```php
use App\Support\RedisFeatureFlagRepository;
use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;

public function register(): void
{
    $this->app->singleton(FeatureFlagRepository::class, RedisFeatureFlagRepository::class);
}
```

The package's `FeatureFlagManager` only depends on the contract — your binding takes over automatically.

## Caching the Eloquent repository

If you want caching without rewriting the repository, wrap it:

```php
final class CachedFeatureFlagRepository implements FeatureFlagRepository
{
    public function __construct(
        private readonly FeatureFlagRepository $inner,
        private readonly Cache $cache,
    ) {}

    public function isEnabled(string $key, ?string $scopeId = null): bool
    {
        return $this->cache->remember(
            "ff:{$key}:".($scopeId ?? 'global'),
            now()->addMinutes(5),
            fn () => $this->inner->isEnabled($key, $scopeId),
        );
    }

    // delegate the rest, invalidate cache on writes
}
```

Bind:

```php
$this->app->singleton(FeatureFlagRepository::class, function ($app) {
    return new CachedFeatureFlagRepository(
        $app->make(EloquentFeatureFlagRepository::class),
        $app->make(Cache::class),
    );
});
```

## Adding columns

1. Add a migration alongside the published one.
2. Subclass `FeatureFlag` and add casts/fillable as needed.
3. Update the published config to point at your model.

The `FeatureTransfer` DTO is final — if you need extra fields exposed, expose them through your own DTO returned by your repository methods (still typed as `FeatureTransfer`), or skip the DTO and read your model directly.

## Next steps

- [Testing](testing.md)
- [Architecture](architecture.md)

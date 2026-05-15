# Testing

## Inside this package

Pest + Orchestra Testbench. Two suites: **Unit** (manager + repository internals) and **Feature** (HTTP, middleware, admin UI end-to-end).

```bash
composer test          # all suites
composer test:unit
composer test:feature
composer stan          # phpstan
```

Migrations run via Testbench's in-memory SQLite. See `tests/TestCase.php` and `tests/Pest.php`.

## In your application

### Enable / disable flags in tests

Just call the manager directly — it writes to the test DB:

```php
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;

it('shows the new dashboard when flag enabled', function () {
    app(FeatureFlagManager::class)->create([
        'key' => 'new-dashboard',
        'value' => true,
        'scope_id' => null,
    ]);

    $this->get('/dashboard')->assertOk();
});
```

### Scope overrides in tests

```php
$manager->create(['key' => 'new-dashboard', 'value' => false, 'scope_id' => null]);
$manager->create(['key' => 'new-dashboard', 'value' => true,  'scope_id' => 'group-A']);

expect($manager->isEnabled('new-dashboard', 'group-A'))->toBeTrue();
expect($manager->isEnabled('new-dashboard'))->toBeFalse();
```

### Faking the repository

For unit tests that don't need DB:

```php
use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;

beforeEach(function () {
    $this->app->instance(FeatureFlagRepository::class, new class implements FeatureFlagRepository {
        private array $store = [];
        public function isEnabled(string $key, ?string $scopeId = null): bool {
            return $this->store["{$key}:{$scopeId}"] ?? $this->store["{$key}:"] ?? false;
        }
        // implement the rest as needed for your test
    });
});
```

### Mocking with mock()

Per the project conventions, prefer `mock()` over `Mockery::mock()`:

```php
mock(FeatureFlagRepository::class)
    ->shouldReceive('isEnabled')
    ->with('new-dashboard', null)
    ->andReturnTrue();
```

### Faking the scope resolver

```php
use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;

$this->app->instance(FeatureScopeResolver::class, new class implements FeatureScopeResolver {
    public function resolve($request): ?string { return 'group-A'; }
});
```

### Test helper trait

Drop this in `tests/` for terser tests:

```php
trait WithFeatureFlags
{
    protected function enableFlag(string $key, ?string $scopeId = null): void
    {
        app(\Chemaclass\FeatureFlags\Manager\FeatureFlagManager::class)
            ->updateOrCreate(
                ['key' => $key, 'scope_id' => $scopeId],
                ['value' => true],
            );
    }

    protected function disableFlag(string $key, ?string $scopeId = null): void
    {
        app(\Chemaclass\FeatureFlags\Manager\FeatureFlagManager::class)
            ->updateOrCreate(
                ['key' => $key, 'scope_id' => $scopeId],
                ['value' => false],
            );
    }
}
```

### Time-window tests

`Carbon::setTestNow()` works as expected — the repository uses `now()`:

```php
Carbon::setTestNow('2026-11-28 12:00');

$manager->create([
    'key' => 'black-friday',
    'value' => true,
    'enabled_from' => '2026-11-27',
    'enabled_until' => '2026-12-01',
]);

expect($manager->isEnabled('black-friday'))->toBeTrue();

Carbon::setTestNow('2026-12-02 12:00');
expect($manager->isEnabled('black-friday'))->toBeFalse();
```

## Next steps

- [Recipes](recipes.md)
- [Architecture](architecture.md)

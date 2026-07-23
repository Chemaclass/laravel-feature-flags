<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\Repository\CachingFeatureFlagRepository;
use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->repo = app(FeatureFlagRepository::class);
});

/**
 * @param  callable():void  $callback
 */
function countQueries(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('memoizes isEnabled within a request (second call hits no DB)', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    $queries = countQueries(function (): void {
        expect($this->repo->isEnabled('k'))->toBeTrue();
        expect($this->repo->isEnabled('k'))->toBeTrue();
        expect($this->repo->isEnabled('k'))->toBeTrue();
    });

    expect($queries)->toBe(1);
});

it('memoizes per key+scope pair independently', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => false]);
    $this->repo->create(['key' => 'k', 'scope_id' => 'g', 'value' => true]);

    $queries = countQueries(function (): void {
        expect($this->repo->isEnabled('k', 'g'))->toBeTrue();
        expect($this->repo->isEnabled('k'))->toBeFalse();
        expect($this->repo->isEnabled('k', 'g'))->toBeTrue();
    });

    expect($queries)->toBe(2);
});

it('allEnabled resolves many keys in a single query', function (): void {
    $this->repo->create(['key' => 'a', 'scope_id' => null, 'value' => true]);
    $this->repo->create(['key' => 'b', 'scope_id' => null, 'value' => false]);
    $this->repo->create(['key' => 'b', 'scope_id' => 'g', 'value' => true]);

    $result = [];
    $queries = countQueries(function () use (&$result): void {
        $result = $this->repo->allEnabled(['a', 'b', 'missing'], 'g');
    });

    expect($queries)->toBe(1)
        ->and($result)->toBe(['a' => true, 'b' => true, 'missing' => false]);
});

it('allEnabled reuses already-memoized keys', function (): void {
    $this->repo->create(['key' => 'a', 'scope_id' => null, 'value' => true]);
    $this->repo->create(['key' => 'b', 'scope_id' => null, 'value' => true]);

    $this->repo->isEnabled('a');

    $queries = countQueries(function (): void {
        expect($this->repo->allEnabled(['a', 'b']))->toBe(['a' => true, 'b' => true]);
    });

    // only 'b' was missing -> one query
    expect($queries)->toBe(1);
});

it('a write invalidates the memo so the next read is fresh', function (): void {
    $dto = $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => false]);

    expect($this->repo->isEnabled('k'))->toBeFalse();

    $this->repo->toggleValue($dto->id);

    expect($this->repo->isEnabled('k'))->toBeTrue();
});

it('behaves identically with no cache store configured (default)', function (): void {
    expect(config('feature-flags.cache.store'))->toBeNull();

    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    expect($this->repo->isEnabled('k'))->toBeTrue();
});

it('uses the persistent store and invalidates it on write', function (): void {
    $inner = app(EloquentFeatureFlagRepository::class);
    $repo = new CachingFeatureFlagRepository(
        $inner,
        app(CacheFactory::class),
        ['store' => 'array', 'ttl' => 60, 'prefix' => 'ff-test'],
    );

    $repo->create(['key' => 'k', 'scope_id' => null, 'value' => false]);
    expect($repo->isEnabled('k'))->toBeFalse();

    // Mutate the row directly (bypassing the decorator) to prove the cache served
    // the value, then confirm a decorator write busts it.
    $inner->updateOrCreate(['key' => 'k', 'scope_id' => null], ['value' => true]);
    // A fresh decorator instance reading the same store still sees the cached false.
    $sameStore = new CachingFeatureFlagRepository(
        $inner,
        app(CacheFactory::class),
        ['store' => 'array', 'ttl' => 60, 'prefix' => 'ff-test'],
    );
    expect($sameStore->isEnabled('k'))->toBeFalse();

    // A write through the decorator bumps the version and busts the cache.
    $sameStore->toggleDevByKey('k');
    expect($sameStore->isEnabled('k'))->toBeTrue();
});

<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Events\FlagsChanged;
use Chemaclass\FeatureFlags\Listeners\InvalidateFlagCache;
use Chemaclass\FeatureFlags\Repository\CachingFeatureFlagRepository;
use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Event;

function realtimeRepo(): CachingFeatureFlagRepository
{
    return new CachingFeatureFlagRepository(
        app(EloquentFeatureFlagRepository::class),
        app(CacheFactory::class),
        ['store' => 'array', 'ttl' => 60, 'prefix' => 'ff-rt'],
    );
}

it('does not broadcast when realtime is disabled', function (): void {
    config()->set('feature-flags.realtime.enabled', false);
    Event::fake([FlagsChanged::class]);

    realtimeRepo()->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    Event::assertNotDispatched(FlagsChanged::class);
});

it('broadcasts FlagsChanged on a write when realtime is enabled', function (): void {
    config()->set('feature-flags.realtime.enabled', true);
    Event::fake([FlagsChanged::class]);

    realtimeRepo()->toggleDevByKey('anything'); // any write triggers flush()

    Event::assertDispatched(FlagsChanged::class);
});

it('the listener bumps the cache version so the next read is fresh', function (): void {
    config()->set('feature-flags.cache', ['store' => 'array', 'ttl' => 60, 'prefix' => 'ff-rt']);

    $repo = realtimeRepo();
    $repo->create(['key' => 'k', 'scope_id' => null, 'value' => false]);

    // Warm the cross-request cache with the current (false) value.
    expect($repo->isEnabled('k'))->toBeFalse();

    // Change the row out-of-band (as another node would), then invalidate.
    app(EloquentFeatureFlagRepository::class)->updateOrCreate(['key' => 'k', 'scope_id' => null], ['value' => true]);

    // A fresh decorator instance still serves the cached false...
    $sameStore = realtimeRepo();
    expect($sameStore->isEnabled('k'))->toBeFalse();

    // ...until the broadcast listener bumps the version.
    app(InvalidateFlagCache::class)->handle(new FlagsChanged);

    expect(realtimeRepo()->isEnabled('k'))->toBeTrue();
});

it('the listener is idempotent for repeated broadcasts', function (): void {
    config()->set('feature-flags.cache', ['store' => 'array', 'ttl' => 60, 'prefix' => 'ff-rt']);
    $listener = app(InvalidateFlagCache::class);

    $listener->handle(new FlagsChanged);
    $listener->handle(new FlagsChanged);

    // No exception, and a subsequent evaluation still works.
    realtimeRepo()->create(['key' => 'k', 'scope_id' => null, 'value' => true]);
    expect(realtimeRepo()->isEnabled('k'))->toBeTrue();
});

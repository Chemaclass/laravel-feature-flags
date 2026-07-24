<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Events\FlagsChanged;
use Chemaclass\FeatureFlags\Events\FlagToggled;
use Chemaclass\FeatureFlags\FeatureFlagsServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;

it('registers audit, realtime and pennant when enabled at boot', function (): void {
    $this->app->register(PennantServiceProvider::class);
    config()->set('pennant.stores.feature-flags', ['driver' => 'feature-flags']);
    config()->set('feature-flags.admin.enabled', false); // avoid re-loading routes
    config()->set('feature-flags.audit.enabled', true);
    config()->set('feature-flags.realtime.enabled', true);
    config()->set('feature-flags.pennant.enabled', true);

    // Re-boot the provider now that the flags are on.
    (new FeatureFlagsServiceProvider($this->app))->boot($this->app->make(Router::class));

    expect(Event::hasListeners(FlagToggled::class))->toBeTrue()
        ->and(Event::hasListeners(FlagsChanged::class))->toBeTrue()
        ->and(Feature::store('feature-flags'))->not->toBeNull(); // pennant driver registered
});

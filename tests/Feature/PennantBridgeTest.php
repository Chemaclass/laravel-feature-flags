<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Pennant\FeatureFlagsPennantDriver;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;

beforeEach(function (): void {
    if (! class_exists(Feature::class)) {
        $this->markTestSkipped('laravel/pennant is not installed.');
    }

    $this->app->register(PennantServiceProvider::class);
    config()->set('pennant.stores.feature-flags', ['driver' => 'feature-flags']);
    config()->set('pennant.default', 'feature-flags');

    Feature::extend('feature-flags', fn ($app): FeatureFlagsPennantDriver => $app->make(FeatureFlagsPennantDriver::class));

    $this->manager = app(FeatureFlagManager::class);
});

it('Feature::active resolves through this package (global)', function (): void {
    $this->manager->create(['key' => 'x', 'scope_id' => null, 'value' => true]);

    expect(Feature::active('x'))->toBeTrue()
        ->and(Feature::inactive('x'))->toBeFalse();
});

it('Feature::active is false for a disabled or missing flag', function (): void {
    $this->manager->create(['key' => 'off', 'scope_id' => null, 'value' => false]);

    expect(Feature::active('off'))->toBeFalse()
        ->and(Feature::active('missing'))->toBeFalse();
});

it('Feature::for(scope) maps a string scope to this package scope id', function (): void {
    $this->manager->create(['key' => 'x', 'scope_id' => null, 'value' => false]);
    $this->manager->create(['key' => 'x', 'scope_id' => 'team-1', 'value' => true]);

    expect(Feature::for('team-1')->active('x'))->toBeTrue()
        ->and(Feature::active('x'))->toBeFalse();
});

it('driver writes and deletes through the manager', function (): void {
    $driver = app(FeatureFlagsPennantDriver::class);

    $driver->set('y', 'team-9', true);
    expect($this->manager->isEnabled('y', 'team-9'))->toBeTrue();

    $driver->delete('y', 'team-9');
    expect($this->manager->findByKeyAndScope('y', 'team-9'))->toBeNull();
});

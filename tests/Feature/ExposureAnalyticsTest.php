<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Analytics\ExposureRecorder;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Models\FeatureFlagExposure;

beforeEach(function (): void {
    $this->manager = app(FeatureFlagManager::class);
    $this->recorder = app(ExposureRecorder::class);
});

it('records nothing when analytics is disabled', function (): void {
    config()->set('feature-flags.analytics.enabled', false);
    $this->manager->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    $this->manager->isEnabled('k');

    expect(FeatureFlagExposure::query()->count())->toBe(0);
});

it('increments an evaluation counter when enabled', function (): void {
    config()->set('feature-flags.analytics.enabled', true);
    $this->manager->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    $this->manager->isEnabled('k');
    $this->manager->isEnabled('k');
    $this->manager->isEnabled('k');

    $row = FeatureFlagExposure::query()->where('key', 'k')->where('variant', '')->first();
    expect($row->count)->toBe(3)
        ->and($row->enabled)->toBeTrue()
        ->and($row->last_seen_at)->not->toBeNull();
});

it('tracks enabled and disabled separately', function (): void {
    config()->set('feature-flags.analytics.enabled', true);
    $dto = $this->manager->create(['key' => 'k', 'scope_id' => null, 'value' => false]);

    $this->manager->isEnabled('k');       // disabled
    $this->manager->toggleValue($dto->id);
    $this->manager->isEnabled('k');       // enabled

    $stats = $this->manager->exposureStats();
    expect($stats)->toHaveCount(1)
        ->and($stats[0]->enabled)->toBe(1)
        ->and($stats[0]->disabled)->toBe(1)
        ->and($stats[0]->total())->toBe(2);
});

it('records variant exposures', function (): void {
    config()->set('feature-flags.analytics.enabled', true);
    $this->manager->create([
        'key' => 'home', 'scope_id' => null, 'value' => true,
        'variants' => [['name' => 'blue', 'weight' => 100]],
    ]);

    $this->manager->variant('home', 'a');
    $this->manager->variant('home', 'b');

    $stats = $this->manager->exposureStats();
    expect($stats[0]->variants)->toBe(['blue' => 2]);
});

it('records exposures for each key in allEnabled', function (): void {
    config()->set('feature-flags.analytics.enabled', true);
    $this->manager->create(['key' => 'a', 'scope_id' => null, 'value' => true]);
    $this->manager->create(['key' => 'b', 'scope_id' => null, 'value' => false]);

    $this->manager->allEnabled(['a', 'b']);

    expect(FeatureFlagExposure::query()->where('key', 'a')->first()->count)->toBe(1)
        ->and(FeatureFlagExposure::query()->where('key', 'b')->first()->count)->toBe(1);
});

it('flag:stats prints a table', function (): void {
    config()->set('feature-flags.analytics.enabled', true);
    $this->manager->create(['key' => 'k', 'scope_id' => null, 'value' => true]);
    $this->manager->isEnabled('k');

    $this->artisan('flag:stats')
        ->expectsOutputToContain('k')
        ->assertExitCode(0);
});

it('flag:stats shows variant breakdown in the table', function (): void {
    config()->set('feature-flags.analytics.enabled', true);
    $this->manager->create([
        'key' => 'home', 'scope_id' => null, 'value' => true,
        'variants' => [['name' => 'blue', 'weight' => 100]],
    ]);
    $this->manager->variant('home', 'a');

    $this->artisan('flag:stats')
        ->expectsOutputToContain('blue=1')
        ->assertExitCode(0);
});

it('flag:stats reports when empty', function (): void {
    $this->artisan('flag:stats')
        ->expectsOutputToContain('No exposures recorded')
        ->assertExitCode(0);
});

it('flag:stats emits JSON', function (): void {
    config()->set('feature-flags.analytics.enabled', true);
    $this->manager->create(['key' => 'k', 'scope_id' => null, 'value' => true]);
    $this->manager->isEnabled('k');

    $this->artisan('flag:stats', ['--json' => true])->assertExitCode(0);

    $stats = $this->manager->exposureStats();
    expect($stats[0]->key)->toBe('k')->and($stats[0]->enabled)->toBe(1);
});

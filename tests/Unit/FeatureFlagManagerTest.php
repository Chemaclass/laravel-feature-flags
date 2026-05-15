<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Contracts\FeatureKey;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;

enum SampleFeature: string implements FeatureKey
{
    case NewDashboard = 'new-dashboard';

    public function key(): string
    {
        return $this->value;
    }
}

it('returns false when flag does not exist', function (): void {
    $manager = app(FeatureFlagManager::class);

    expect($manager->isEnabled(SampleFeature::NewDashboard))->toBeFalse();
});

it('returns true when global flag enabled', function (): void {
    $manager = app(FeatureFlagManager::class);
    $manager->create(['key' => 'new-dashboard', 'value' => true, 'scope_id' => null]);

    expect($manager->isEnabled(SampleFeature::NewDashboard))->toBeTrue();
});

it('scope override beats global', function (): void {
    $manager = app(FeatureFlagManager::class);
    $manager->create(['key' => 'new-dashboard', 'value' => false, 'scope_id' => null]);
    $manager->create(['key' => 'new-dashboard', 'value' => true, 'scope_id' => 'tenant-1']);

    expect($manager->isEnabled(SampleFeature::NewDashboard, 'tenant-1'))->toBeTrue();
    expect($manager->isEnabled(SampleFeature::NewDashboard))->toBeFalse();
});

<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Facades\FeatureFlag;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;

it('facade resolves to the manager', function (): void {
    expect(FeatureFlag::getFacadeRoot())->toBeInstanceOf(FeatureFlagManager::class);
});

it('facade calls forward to the manager', function (): void {
    FeatureFlag::create(['key' => 'facade-flag', 'scope_id' => null, 'value' => true]);

    expect(FeatureFlag::isEnabled('facade-flag'))->toBeTrue();
});

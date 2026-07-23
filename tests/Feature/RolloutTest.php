<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;

beforeEach(function (): void {
    $this->repo = app(EloquentFeatureFlagRepository::class);
});

it('null rollout_percentage keeps pure boolean behaviour', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    expect($this->repo->isEnabled('k', 'anyone'))->toBeTrue();
});

it('0% is always off, 100% is always on', function (): void {
    $this->repo->create(['key' => 'off', 'scope_id' => null, 'value' => true, 'rollout_percentage' => 0]);
    $this->repo->create(['key' => 'on', 'scope_id' => null, 'value' => true, 'rollout_percentage' => 100]);

    expect($this->repo->isEnabled('off', 'scope-a'))->toBeFalse()
        ->and($this->repo->isEnabled('on', 'scope-a'))->toBeTrue();
});

it('value=false disables even at 100%', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => false, 'rollout_percentage' => 100]);

    expect($this->repo->isEnabled('k', 'scope-a'))->toBeFalse();
});

it('is deterministic for the same key+scope', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true, 'rollout_percentage' => 50]);

    $first = $this->repo->isEnabled('k', 'scope-X');
    for ($i = 0; $i < 20; $i++) {
        expect($this->repo->isEnabled('k', 'scope-X'))->toBe($first);
    }
});

it('enables roughly the configured percentage of scopes', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true, 'rollout_percentage' => 30]);

    $enabled = 0;
    $total = 2000;
    for ($i = 0; $i < $total; $i++) {
        if ($this->repo->isEnabled('k', 'scope-'.$i)) {
            $enabled++;
        }
    }

    $ratio = $enabled / $total;
    // 30% target, allow generous tolerance for hash distribution.
    expect($ratio)->toBeGreaterThan(0.25)->toBeLessThan(0.35);
});

it('scope override wins and applies its own rollout', function (): void {
    // Global fully on, scope row gated at 0% -> scope wins -> disabled for that scope.
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true]);
    $this->repo->create(['key' => 'k', 'scope_id' => 'team-9', 'value' => true, 'rollout_percentage' => 0]);

    expect($this->repo->isEnabled('k', 'team-9'))->toBeFalse()
        ->and($this->repo->isEnabled('k', 'other'))->toBeTrue();
});

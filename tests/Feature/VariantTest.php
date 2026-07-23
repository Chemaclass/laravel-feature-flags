<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\DTO\VariantResult;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;

beforeEach(function (): void {
    $this->manager = app(FeatureFlagManager::class);
    $this->repo = app(EloquentFeatureFlagRepository::class);
});

it('returns null when the flag has no variants', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    expect($this->manager->variant('k'))->toBeNull();
});

it('returns null when the flag is disabled', function (): void {
    $this->repo->create([
        'key' => 'k', 'scope_id' => null, 'value' => false,
        'variants' => [['name' => 'a', 'weight' => 100]],
    ]);

    expect($this->manager->variant('k'))->toBeNull();
});

it('selects a variant with its payload', function (): void {
    $this->repo->create([
        'key' => 'k', 'scope_id' => null, 'value' => true,
        'variants' => [['name' => 'blue', 'weight' => 100]],
        'variant_payloads' => ['blue' => ['hex' => '#00f']],
    ]);

    $result = $this->manager->variant('k', 'scope-1');

    expect($result)->toBeInstanceOf(VariantResult::class)
        ->and($result->name)->toBe('blue')
        ->and($result->payload)->toBe(['hex' => '#00f']);
});

it('is deterministic for the same key+scope', function (): void {
    $this->repo->create([
        'key' => 'k', 'scope_id' => null, 'value' => true,
        'variants' => [['name' => 'a', 'weight' => 50], ['name' => 'b', 'weight' => 50]],
    ]);

    $first = $this->manager->variant('k', 'scope-X')->name;
    for ($i = 0; $i < 20; $i++) {
        expect($this->manager->variant('k', 'scope-X')->name)->toBe($first);
    }
});

it('approximates the configured weight distribution', function (): void {
    $this->repo->create([
        'key' => 'k', 'scope_id' => null, 'value' => true,
        'variants' => [['name' => 'a', 'weight' => 80], ['name' => 'b', 'weight' => 20]],
    ]);

    $counts = ['a' => 0, 'b' => 0];
    $total = 2000;
    for ($i = 0; $i < $total; $i++) {
        $counts[$this->manager->variant('k', 'scope-'.$i)->name]++;
    }

    expect($counts['a'] / $total)->toBeGreaterThan(0.74)->toBeLessThan(0.86);
});

<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Variants\VariantSelector;

beforeEach(function (): void {
    $this->selector = new VariantSelector;
});

it('returns null when there are no variants', function (): void {
    expect($this->selector->select([], 'k', null))->toBeNull();
});

it('skips variants with zero/negative weight or missing name', function (): void {
    $variants = [
        ['name' => 'skip-zero', 'weight' => 0],
        ['name' => 'skip-neg', 'weight' => -5],
        ['weight' => 50], // missing name
        ['name' => 'only', 'weight' => 10],
    ];

    // Every valid pick must be the single non-skipped variant.
    foreach (['a', 'b', 'c', 'd'] as $scope) {
        expect($this->selector->select($variants, 'k', $scope))->toBe('only');
    }
});

it('returns null when all variants are zero-weight', function (): void {
    $variants = [['name' => 'a', 'weight' => 0], ['name' => 'b', 'weight' => 0]];

    expect($this->selector->select($variants, 'k', null))->toBeNull();
});

it('always returns one of the defined names', function (): void {
    $variants = [['name' => 'a', 'weight' => 1], ['name' => 'b', 'weight' => 1]];

    for ($i = 0; $i < 50; $i++) {
        expect($this->selector->select($variants, 'k', 'scope-'.$i))->toBeIn(['a', 'b']);
    }
});

<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;

beforeEach(function (): void {
    $this->manager = app(FeatureFlagManager::class);
    $this->repo = app(EloquentFeatureFlagRepository::class);
});

it('no rules behaves exactly like a boolean flag', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    expect($this->manager->isEnabled('k', null, ['plan' => 'free']))->toBeTrue();
});

it('a matching rule overrides the boolean value', function (): void {
    $this->repo->create([
        'key' => 'k',
        'scope_id' => null,
        'value' => false,
        'rules' => [
            ['when' => [['attr' => 'plan', 'op' => 'eq', 'value' => 'pro']], 'then' => true],
        ],
    ]);

    expect($this->manager->isEnabled('k', null, ['plan' => 'pro']))->toBeTrue()
        ->and($this->manager->isEnabled('k', null, ['plan' => 'free']))->toBeFalse();
});

it('a non-matching rule falls back to the boolean value', function (): void {
    $this->repo->create([
        'key' => 'k',
        'scope_id' => null,
        'value' => true,
        'rules' => [
            ['when' => [['attr' => 'country', 'op' => 'in', 'value' => ['DE']]], 'then' => false],
        ],
    ]);

    // context has no matching country -> fall back to value=true
    expect($this->manager->isEnabled('k', null, ['country' => 'US']))->toBeTrue();
});

it('allEnabled applies rules per key', function (): void {
    $this->repo->create([
        'key' => 'a', 'scope_id' => null, 'value' => false,
        'rules' => [['when' => [['attr' => 'beta', 'op' => 'eq', 'value' => true]], 'then' => true]],
    ]);
    $this->repo->create(['key' => 'b', 'scope_id' => null, 'value' => true]);

    expect($this->repo->allEnabled(['a', 'b'], null, ['beta' => true]))
        ->toBe(['a' => true, 'b' => true]);
});

it('caching keys on the context so different contexts do not collide', function (): void {
    // Bound repo is the caching decorator.
    $repo = app(FeatureFlagRepository::class);
    $repo->create([
        'key' => 'k', 'scope_id' => null, 'value' => false,
        'rules' => [['when' => [['attr' => 'plan', 'op' => 'eq', 'value' => 'pro']], 'then' => true]],
    ]);

    expect($repo->isEnabled('k', null, ['plan' => 'pro']))->toBeTrue()
        ->and($repo->isEnabled('k', null, ['plan' => 'free']))->toBeFalse()
        ->and($repo->isEnabled('k', null, ['plan' => 'pro']))->toBeTrue();
});

<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;

beforeEach(function (): void {
    $this->repo = app(EloquentFeatureFlagRepository::class);
});

it('no prerequisites behaves normally', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    expect($this->repo->isEnabled('k'))->toBeTrue();
});

it('a satisfied prerequisite lets the flag through', function (): void {
    $this->repo->create(['key' => 'base', 'scope_id' => null, 'value' => true]);
    $this->repo->create(['key' => 'child', 'scope_id' => null, 'value' => true, 'prerequisites' => ['base']]);

    expect($this->repo->isEnabled('child'))->toBeTrue();
});

it('an unsatisfied prerequisite forces the flag off', function (): void {
    $this->repo->create(['key' => 'base', 'scope_id' => null, 'value' => false]);
    $this->repo->create(['key' => 'child', 'scope_id' => null, 'value' => true, 'prerequisites' => ['base']]);

    expect($this->repo->isEnabled('child'))->toBeFalse();
});

it('chains prerequisites transitively', function (): void {
    $this->repo->create(['key' => 'a', 'scope_id' => null, 'value' => false]);
    $this->repo->create(['key' => 'b', 'scope_id' => null, 'value' => true, 'prerequisites' => ['a']]);
    $this->repo->create(['key' => 'c', 'scope_id' => null, 'value' => true, 'prerequisites' => ['b']]);

    expect($this->repo->isEnabled('c'))->toBeFalse(); // a is off -> b off -> c off
});

it('does not hang on a prerequisite cycle', function (): void {
    $this->repo->create(['key' => 'x', 'scope_id' => null, 'value' => true, 'prerequisites' => ['y']]);
    $this->repo->create(['key' => 'y', 'scope_id' => null, 'value' => true, 'prerequisites' => ['x']]);

    expect($this->repo->isEnabled('x'))->toBeFalse();
});

it('kill switch forces a key off regardless of value', function (): void {
    config()->set('feature-flags.kill_switch', ['dead']);
    $this->repo->create(['key' => 'dead', 'scope_id' => null, 'value' => true]);

    expect($this->repo->isEnabled('dead'))->toBeFalse();
});

it('kill switch and prerequisites apply in allEnabled too', function (): void {
    config()->set('feature-flags.kill_switch', ['dead']);
    $this->repo->create(['key' => 'dead', 'scope_id' => null, 'value' => true]);
    $this->repo->create(['key' => 'base', 'scope_id' => null, 'value' => false]);
    $this->repo->create(['key' => 'child', 'scope_id' => null, 'value' => true, 'prerequisites' => ['base']]);
    $this->repo->create(['key' => 'ok', 'scope_id' => null, 'value' => true]);

    expect($this->repo->allEnabled(['dead', 'child', 'ok']))
        ->toBe(['dead' => false, 'child' => false, 'ok' => true]);
});

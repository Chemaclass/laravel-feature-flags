<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Models\FeatureFlagSegment;
use Chemaclass\FeatureFlags\Targeting\SegmentRepository;

beforeEach(function (): void {
    $this->manager = app(FeatureFlagManager::class);
});

it('defines and lists a segment', function (): void {
    $this->manager->defineSegment('eu-pro', [
        ['attr' => 'plan', 'op' => 'eq', 'value' => 'pro'],
    ], 'EU pro users');

    expect($this->manager->segments())->toHaveKey('eu-pro')
        ->and(FeatureFlagSegment::query()->where('name', 'eu-pro')->first()->description)->toBe('EU pro users');
});

it('a flag rule referencing a segment evaluates through it', function (): void {
    $this->manager->defineSegment('eu-pro', [
        ['attr' => 'plan', 'op' => 'eq', 'value' => 'pro'],
        ['attr' => 'country', 'op' => 'in', 'value' => ['DE', 'AT']],
    ]);
    $this->manager->create([
        'key' => 'k', 'scope_id' => null, 'value' => false,
        'rules' => [['when' => [['segment' => 'eu-pro']], 'then' => true]],
    ]);

    expect($this->manager->isEnabled('k', null, ['plan' => 'pro', 'country' => 'DE']))->toBeTrue()
        ->and($this->manager->isEnabled('k', null, ['plan' => 'pro', 'country' => 'US']))->toBeFalse()
        ->and($this->manager->isEnabled('k', null, ['plan' => 'free', 'country' => 'DE']))->toBeFalse();
});

it('updating a segment changes evaluation for every flag using it', function (): void {
    $this->manager->defineSegment('s', [['attr' => 'plan', 'op' => 'eq', 'value' => 'pro']]);
    $this->manager->create([
        'key' => 'k', 'scope_id' => null, 'value' => false,
        'rules' => [['when' => [['segment' => 's']], 'then' => true]],
    ]);

    expect($this->manager->isEnabled('k', null, ['plan' => 'pro']))->toBeTrue();

    // Redefining a segment invalidates cached evaluations of flags using it.
    $this->manager->defineSegment('s', [['attr' => 'plan', 'op' => 'eq', 'value' => 'enterprise']]);

    expect($this->manager->isEnabled('k', null, ['plan' => 'pro']))->toBeFalse()
        ->and($this->manager->isEnabled('k', null, ['plan' => 'enterprise']))->toBeTrue();
});

it('deletes a segment', function (): void {
    $this->manager->defineSegment('s', [['attr' => 'plan', 'op' => 'eq', 'value' => 'pro']]);

    expect($this->manager->deleteSegment('s'))->toBeTrue()
        ->and($this->manager->segments())->not->toHaveKey('s')
        ->and($this->manager->deleteSegment('missing'))->toBeFalse();
});

it('memoizes segment loading within a request', function (): void {
    $repo = app(SegmentRepository::class);
    $repo->define('s', [['attr' => 'a', 'op' => 'eq', 'value' => 1]]);

    $first = $repo->all();
    $second = $repo->all();

    expect($second)->toBe($first)->toHaveKey('s');
});

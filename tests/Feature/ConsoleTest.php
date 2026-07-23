<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;

beforeEach(function (): void {
    $this->manager = app(FeatureFlagManager::class);
});

it('flag:create creates a flag through the manager', function (): void {
    $this->artisan('flag:create', ['key' => 'new-thing', '--value' => '1', '--hint' => 'wip'])
        ->assertExitCode(0);

    expect($this->manager->isEnabled('new-thing'))->toBeTrue()
        ->and($this->manager->findByKeyAndScope('new-thing', null)->hint)->toBe('wip');
});

it('flag:create respects a falsey value and a scope', function (): void {
    $this->artisan('flag:create', ['key' => 'k', '--value' => '0', '--scope' => 'team-1'])
        ->assertExitCode(0);

    expect($this->manager->findByKeyAndScope('k', 'team-1')->value)->toBeFalse();
});

it('flag:list shows flags for a scope', function (): void {
    $this->manager->create(['key' => 'a', 'scope_id' => null, 'value' => true]);
    $this->manager->create(['key' => 'b', 'scope_id' => null, 'value' => false]);

    $this->artisan('flag:list')
        ->expectsTable(['Key', 'Value'], [['a', 'enabled'], ['b', 'disabled']])
        ->assertExitCode(0);
});

it('flag:list reports when empty', function (): void {
    $this->artisan('flag:list')
        ->expectsOutputToContain('No feature flags found.')
        ->assertExitCode(0);
});

it('flag:toggle flips a flag and reports the new value', function (): void {
    $this->manager->create(['key' => 'k', 'scope_id' => null, 'value' => false]);

    $this->artisan('flag:toggle', ['key' => 'k'])
        ->expectsOutputToContain('enabled')
        ->assertExitCode(0);

    expect($this->manager->isEnabled('k'))->toBeTrue();
});

it('flag:toggle fails when the flag is missing', function (): void {
    $this->artisan('flag:toggle', ['key' => 'nope'])
        ->assertExitCode(1);
});

it('flag:delete removes a flag by id', function (): void {
    $dto = $this->manager->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    $this->artisan('flag:delete', ['id' => $dto->id])
        ->assertExitCode(0);

    expect($this->manager->findById($dto->id))->toBeNull();
});

it('flag:delete fails for an unknown id', function (): void {
    $this->artisan('flag:delete', ['id' => '01ZZZZZZZZZZZZZZZZZZZZZZZZ'])
        ->assertExitCode(1);
});

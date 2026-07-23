<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Models\FeatureFlag;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->manager = app(FeatureFlagManager::class);
});

function ageFlag(string $key, bool $value, int $daysAgo, ?string $scope = null): void
{
    $flag = app(FeatureFlagManager::class)->create(['key' => $key, 'scope_id' => $scope, 'value' => $value]);
    FeatureFlag::query()->whereKey($flag->id)->update(['updated_at' => Carbon::now()->subDays($daysAgo)]);
}

it('lists a flag untouched beyond the cutoff', function (): void {
    ageFlag('old', true, 60);

    $this->artisan('flag:stale', ['--days' => 30])
        ->expectsOutputToContain('old')
        ->assertExitCode(0);
});

it('does not list a freshly updated flag', function (): void {
    ageFlag('fresh', true, 2);

    $this->artisan('flag:stale', ['--days' => 30])
        ->expectsOutputToContain('No flags unchanged')
        ->assertExitCode(0);
});

it('does not list a flag with inconsistent values across rows', function (): void {
    ageFlag('mixed', true, 60, null);
    ageFlag('mixed', false, 60, 'team-1');

    $this->artisan('flag:stale', ['--days' => 30])
        ->expectsOutputToContain('No flags unchanged')
        ->assertExitCode(0);
});

it('ignores time-windowed flags', function (): void {
    $flag = $this->manager->create(['key' => 'windowed', 'scope_id' => null, 'value' => true, 'enabled_until' => now()->addYear()]);
    FeatureFlag::query()->whereKey($flag->id)->update(['updated_at' => now()->subDays(90)]);

    $this->artisan('flag:stale', ['--days' => 30])
        ->expectsOutputToContain('No flags unchanged')
        ->assertExitCode(0);
});

it('emits valid JSON with --json', function (): void {
    ageFlag('old', false, 45);

    $this->artisan('flag:stale', ['--days' => 30, '--json' => true])->assertExitCode(0);

    $stale = $this->manager->staleFlags(30);
    expect($stale)->toHaveCount(1)
        ->and($stale[0]['key'])->toBe('old')
        ->and($stale[0]['value'])->toBeFalse();
});

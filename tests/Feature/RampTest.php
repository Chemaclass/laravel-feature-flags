<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->repo = app(EloquentFeatureFlagRepository::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('ramps the effective rollout percentage over time', function (): void {
    $this->repo->create([
        'key' => 'k', 'scope_id' => null, 'value' => true,
        'ramp' => ['from' => 0, 'to' => 100, 'starts_at' => '2026-08-01', 'ends_at' => '2026-08-11'],
    ]);

    // Before the window: 0% → nobody in.
    Carbon::setTestNow('2026-07-01');
    $enabledBefore = collect(range(1, 300))->filter(fn ($i) => $this->repo->isEnabled('k', 'u'.$i))->count();

    // After the window: 100% → everybody in.
    Carbon::setTestNow('2026-09-01');
    $enabledAfter = collect(range(1, 300))->filter(fn ($i) => $this->repo->isEnabled('k', 'u'.$i))->count();

    expect($enabledBefore)->toBe(0)
        ->and($enabledAfter)->toBe(300);
});

it('grows the enabled share monotonically as the ramp progresses', function (): void {
    $this->repo->create([
        'key' => 'k', 'scope_id' => null, 'value' => true,
        'ramp' => ['from' => 0, 'to' => 100, 'starts_at' => '2026-08-01', 'ends_at' => '2026-08-11'],
    ]);

    $countAt = function (string $when): int {
        Carbon::setTestNow($when);

        return collect(range(1, 400))->filter(fn ($i) => $this->repo->isEnabled('k', 'u'.$i))->count();
    };

    $quarter = $countAt('2026-08-03 12:00:00'); // ~25%
    $half = $countAt('2026-08-06 00:00:00');     // ~50%
    $threeq = $countAt('2026-08-08 12:00:00');   // ~75%

    expect($quarter)->toBeLessThan($half)
        ->and($half)->toBeLessThan($threeq)
        ->and($half / 400)->toBeGreaterThan(0.4)->toBeLessThan(0.6);
});

it('falls back to the stored rollout_percentage without a ramp', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true, 'rollout_percentage' => 100]);

    expect($this->repo->isEnabled('k', 'anyone'))->toBeTrue();
});

it('a disabled flag stays off regardless of the ramp', function (): void {
    Carbon::setTestNow('2026-09-01');
    $this->repo->create([
        'key' => 'k', 'scope_id' => null, 'value' => false,
        'ramp' => ['from' => 100, 'to' => 100, 'starts_at' => '2026-08-01', 'ends_at' => '2026-08-11'],
    ]);

    expect($this->repo->isEnabled('k', 'u1'))->toBeFalse();
});

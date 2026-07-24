<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Targeting\RampCalculator;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->calc = new RampCalculator;
    $this->ramp = [
        'from' => 0,
        'to' => 100,
        'starts_at' => '2026-08-01 00:00:00',
        'ends_at' => '2026-08-11 00:00:00', // 10-day ramp
    ];
});

it('returns null for an incomplete ramp', function (): void {
    expect($this->calc->effectivePercentage(['from' => 0]))->toBeNull()
        ->and($this->calc->effectivePercentage([]))->toBeNull();
});

it('returns null when from/to are not numeric', function (): void {
    expect($this->calc->effectivePercentage([
        'from' => 'x', 'to' => 100, 'starts_at' => '2026-08-01', 'ends_at' => '2026-08-11',
    ]))->toBeNull();
});

it('is the start percentage before the window', function (): void {
    expect($this->calc->effectivePercentage($this->ramp, Carbon::parse('2026-07-01')))->toBe(0);
});

it('is the end percentage after the window', function (): void {
    expect($this->calc->effectivePercentage($this->ramp, Carbon::parse('2026-09-01')))->toBe(100);
});

it('interpolates linearly within the window', function (): void {
    // 5 days into a 10-day 0→100 ramp = 50%.
    expect($this->calc->effectivePercentage($this->ramp, Carbon::parse('2026-08-06 00:00:00')))->toBe(50)
        // 2.5 days in = 25%.
        ->and($this->calc->effectivePercentage($this->ramp, Carbon::parse('2026-08-03 12:00:00')))->toBe(25);
});

it('clamps and handles a zero-length or inverted window', function (): void {
    $bad = ['from' => 30, 'to' => 90, 'starts_at' => '2026-08-01', 'ends_at' => '2026-08-01'];

    expect($this->calc->effectivePercentage($bad, Carbon::parse('2026-08-05')))->toBe(30);
});

it('uses now() when no time is given', function (): void {
    Carbon::setTestNow('2026-08-06 00:00:00');

    expect($this->calc->effectivePercentage($this->ramp))->toBe(50);

    Carbon::setTestNow();
});

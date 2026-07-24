<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Targeting;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Turns a scheduled ramp into the rollout percentage that applies right now, by
 * linearly interpolating between `from` and `to` over the `starts_at`→`ends_at`
 * window. Before the window it's `from`; after, `to`. Returns null for an
 * incomplete/invalid ramp so the caller keeps the flag's stored percentage.
 *
 * Ramp shape: ['from' => 5, 'to' => 100, 'starts_at' => ..., 'ends_at' => ...]
 */
final class RampCalculator
{
    /**
     * @param  array<string, mixed>  $ramp
     */
    public function effectivePercentage(array $ramp, ?DateTimeInterface $now = null): ?int
    {
        if (! isset($ramp['from'], $ramp['to'], $ramp['starts_at'], $ramp['ends_at'])) {
            return null;
        }
        if (! is_numeric($ramp['from']) || ! is_numeric($ramp['to'])) {
            return null;
        }

        $from = (int) $ramp['from'];
        $to = (int) $ramp['to'];
        $start = Carbon::parse($ramp['starts_at']);
        $end = Carbon::parse($ramp['ends_at']);
        $now = $now !== null ? Carbon::instance(Carbon::parse($now)) : Carbon::now();

        if ($end->lessThanOrEqualTo($start) || $now->lessThanOrEqualTo($start)) {
            return $this->clamp($from);
        }
        if ($now->greaterThanOrEqualTo($end)) {
            return $this->clamp($to);
        }

        $fraction = ($now->getTimestamp() - $start->getTimestamp()) / ($end->getTimestamp() - $start->getTimestamp());

        return $this->clamp((int) round($from + $fraction * ($to - $from)));
    }

    private function clamp(int $percentage): int
    {
        return max(0, min(100, $percentage));
    }
}

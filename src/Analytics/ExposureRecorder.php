<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Analytics;

use Chemaclass\FeatureFlags\DTO\ExposureStats;
use Chemaclass\FeatureFlags\Models\FeatureFlagExposure;

/**
 * Records aggregate flag exposures and reads them back. Self-guarding: every
 * write is a no-op unless `feature-flags.analytics.enabled` is true, so callers
 * can invoke it unconditionally.
 */
final class ExposureRecorder
{
    public function recordEvaluation(string $key, bool $enabled): void
    {
        $this->increment($key, '', $enabled);
    }

    public function recordVariant(string $key, string $variant): void
    {
        $this->increment($key, $variant, true);
    }

    private function increment(string $key, string $variant, bool $enabled): void
    {
        if (! (bool) config('feature-flags.analytics.enabled', false)) {
            return;
        }

        $row = FeatureFlagExposure::query()->firstOrCreate(
            ['key' => $key, 'variant' => $variant, 'enabled' => $enabled],
            ['count' => 0],
        );

        $row->forceFill(['count' => $row->count + 1, 'last_seen_at' => now()])->save();
    }

    /**
     * Aggregate stats per key: on/off evaluation counts and variant splits.
     *
     * @return list<ExposureStats>
     */
    public function stats(): array
    {
        $rows = FeatureFlagExposure::query()->orderBy('key')->get();

        /** @var array<string, array{enabled: int, disabled: int, variants: array<string, int>}> $byKey */
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row->key] ??= ['enabled' => 0, 'disabled' => 0, 'variants' => []];

            if ($row->variant !== '') {
                $byKey[$row->key]['variants'][$row->variant] = $row->count;

                continue;
            }

            $byKey[$row->key][$row->enabled ? 'enabled' : 'disabled'] += $row->count;
        }

        $stats = [];
        foreach ($byKey as $key => $data) {
            $stats[] = new ExposureStats($key, $data['enabled'], $data['disabled'], $data['variants']);
        }

        return $stats;
    }
}

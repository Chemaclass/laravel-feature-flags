<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Console;

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Console\Command;

final class StatsCommand extends Command
{
    protected $signature = 'flag:stats {--json : Output JSON}';

    protected $description = 'Show aggregate flag exposure counts (requires analytics enabled)';

    public function handle(FeatureFlagManager $manager): int
    {
        $stats = $manager->exposureStats();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(array_map(static fn ($s): array => [
                'key' => $s->key,
                'enabled' => $s->enabled,
                'disabled' => $s->disabled,
                'total' => $s->total(),
                'variants' => $s->variants,
            ], $stats), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($stats === []) {
            $this->info('No exposures recorded yet.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($stats as $stat) {
            $variants = [];
            foreach ($stat->variants as $name => $count) {
                $variants[] = "{$name}={$count}";
            }
            $rows[] = [
                $stat->key,
                $stat->enabled,
                $stat->disabled,
                $stat->total(),
                $variants === [] ? '—' : implode(', ', $variants),
            ];
        }

        $this->table(['Key', 'Enabled', 'Disabled', 'Total', 'Variants'], $rows);

        return self::SUCCESS;
    }
}

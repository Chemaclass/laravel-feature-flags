<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Console;

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Console\Command;

final class StaleFlagsCommand extends Command
{
    protected $signature = 'flag:stale {--days=30 : Consider a flag stale after this many days unchanged} {--json : Output JSON}';

    protected $description = 'List feature flags that look safe to retire (unchanged and constant)';

    public function handle(FeatureFlagManager $manager): int
    {
        $days = (int) $this->option('days');
        $stale = $manager->staleFlags($days);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($stale, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($stale === []) {
            $this->info("No flags unchanged for {$days}+ days.");

            return self::SUCCESS;
        }

        $rows = array_map(
            static fn (array $s): array => [$s['key'], $s['value'] ? 'enabled' : 'disabled', $s['days'].'d ago'],
            $stale,
        );

        $this->warn(count($stale)." flag(s) unchanged for {$days}+ days — candidates for cleanup:");
        $this->table(['Key', 'Constant value', 'Last changed'], $rows);

        return self::SUCCESS;
    }
}

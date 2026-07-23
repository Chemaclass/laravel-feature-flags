<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Console;

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Console\Command;

final class ListFlagsCommand extends Command
{
    protected $signature = 'flag:list {--scope= : Resolve values for this scope id (default: global only)}';

    protected $description = 'List feature flags and their effective value';

    public function handle(FeatureFlagManager $manager): int
    {
        /** @var string|null $scope */
        $scope = $this->option('scope');

        $flags = $manager->all($scope);

        if ($flags === []) {
            $this->info('No feature flags found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($flags as $key => $value) {
            $rows[] = [$key, $value ? 'enabled' : 'disabled'];
        }

        $this->table(['Key', 'Value'.($scope !== null ? " (scope: {$scope})" : '')], $rows);

        return self::SUCCESS;
    }
}

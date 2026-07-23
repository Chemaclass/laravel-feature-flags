<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Console;

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Console\Command;

final class ToggleFlagCommand extends Command
{
    protected $signature = 'flag:toggle {key : The flag key} {--scope= : Toggle the row for this scope id (default: global)}';

    protected $description = 'Toggle a feature flag value on or off';

    public function handle(FeatureFlagManager $manager): int
    {
        /** @var string $key */
        $key = $this->argument('key');
        /** @var string|null $scope */
        $scope = $this->option('scope');

        $flag = $manager->findByKeyAndScope($key, $scope);

        if ($flag === null) {
            $this->error("Flag [{$key}] not found".($scope !== null ? " for scope [{$scope}]" : '').'.');

            return self::FAILURE;
        }

        $value = $manager->toggleValue($flag->id);

        $this->info("Flag [{$key}] is now ".($value ? 'enabled' : 'disabled').'.');

        return self::SUCCESS;
    }
}

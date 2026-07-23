<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Console;

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Console\Command;

final class DeleteFlagCommand extends Command
{
    protected $signature = 'flag:delete {id : The flag row id}';

    protected $description = 'Delete a feature flag row by id';

    public function handle(FeatureFlagManager $manager): int
    {
        /** @var string $id */
        $id = $this->argument('id');

        if (! $manager->delete($id)) {
            $this->error("Flag [{$id}] not found.");

            return self::FAILURE;
        }

        $this->info("Flag [{$id}] deleted.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Console;

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Console\Command;

final class CreateFlagCommand extends Command
{
    protected $signature = 'flag:create
        {key : The flag key}
        {--value=1 : Initial value (1/0, true/false)}
        {--scope= : Scope id (default: global)}
        {--hint= : Optional description}';

    protected $description = 'Create or update a feature flag';

    public function handle(FeatureFlagManager $manager): int
    {
        /** @var string $key */
        $key = $this->argument('key');
        /** @var string|null $scope */
        $scope = $this->option('scope');
        /** @var string|null $hint */
        $hint = $this->option('hint');

        $value = filter_var($this->option('value'), FILTER_VALIDATE_BOOLEAN);

        $flag = $manager->updateOrCreate(
            ['key' => $key, 'scope_id' => $scope],
            ['value' => $value, 'hint' => $hint],
        );

        $this->info("Flag [{$key}] saved as ".($flag->value ? 'enabled' : 'disabled').' (id: '.$flag->id.').');

        return self::SUCCESS;
    }
}

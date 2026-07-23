<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Console;

use Chemaclass\FeatureFlags\DTO\FeatureTransfer;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Sync\FlagDefinitionLoader;
use Illuminate\Console\Command;

final class SyncFlagsCommand extends Command
{
    protected $signature = 'flag:sync
        {--path= : Definitions file (defaults to config feature-flags.sync.path)}
        {--prune : Delete DB flags not present in the file}
        {--dry-run : Show the plan without writing}';

    protected $description = 'Reconcile feature flags from a definitions file into the database';

    public function handle(FeatureFlagManager $manager, FlagDefinitionLoader $loader): int
    {
        /** @var string $path */
        $path = $this->option('path') ?: (string) config('feature-flags.sync.path', base_path('feature-flags.php'));
        $dryRun = (bool) $this->option('dry-run');
        $prune = (bool) $this->option('prune');

        $definitions = $loader->load($path);

        $existing = [];
        foreach ($manager->allFlags() as $flag) {
            $existing[$loader->identityOf($flag->key, $flag->scopeId, $flag->environment)] = $flag;
        }

        $created = $updated = $deleted = $unchanged = 0;

        foreach ($definitions as $identity => $definition) {
            $current = $existing[$identity] ?? null;

            if ($current === null) {
                $created++;
                $this->line('<info>+ create</info> '.$identity);
            } elseif ($this->differs($current, $definition)) {
                $updated++;
                $this->line('<comment>~ update</comment> '.$identity);
            } else {
                $unchanged++;

                continue;
            }

            if (! $dryRun) {
                $manager->updateOrCreate(
                    ['key' => $definition['key'], 'scope_id' => $definition['scope_id'], 'environment' => $definition['environment']],
                    [
                        'value' => $definition['value'],
                        'rollout_percentage' => $definition['rollout_percentage'],
                        'hint' => $definition['hint'],
                        'is_dev' => $definition['is_dev'],
                    ],
                );
            }
        }

        if ($prune) {
            foreach ($existing as $identity => $flag) {
                if (isset($definitions[$identity])) {
                    continue;
                }
                $deleted++;
                $this->line('<fg=red>- delete</> '.$identity);
                if (! $dryRun) {
                    $manager->delete($flag->id);
                }
            }
        }

        $verb = $dryRun ? 'Planned' : 'Applied';
        $this->info("{$verb}: {$created} created, {$updated} updated, {$deleted} deleted, {$unchanged} unchanged.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function differs(FeatureTransfer $current, array $definition): bool
    {
        return $current->value !== $definition['value']
            || $current->rolloutPercentage !== $definition['rollout_percentage']
            || $current->hint !== $definition['hint']
            || $current->isDev !== $definition['is_dev'];
    }
}

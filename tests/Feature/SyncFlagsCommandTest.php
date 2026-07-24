<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;

beforeEach(function (): void {
    $this->manager = app(FeatureFlagManager::class);
    $this->path = sys_get_temp_dir().'/ff_defs_'.uniqid().'.php';
});

afterEach(function (): void {
    @unlink($this->path);
});

/**
 * @param  array<int, array<string, mixed>>  $defs
 */
function writeDefs(string $path, array $defs): void
{
    file_put_contents($path, '<?php return '.var_export($defs, true).';');
}

it('creates flags from the definitions file', function (): void {
    writeDefs($this->path, [
        ['key' => 'a', 'value' => true],
        ['key' => 'b', 'value' => false, 'rollout_percentage' => 20],
    ]);

    $this->artisan('flag:sync', ['--path' => $this->path])->assertExitCode(0);

    expect($this->manager->isEnabled('a'))->toBeTrue()
        ->and($this->manager->findByKeyAndScope('b', null)->rolloutPercentage)->toBe(20);
});

it('is idempotent: a second run changes nothing', function (): void {
    writeDefs($this->path, [['key' => 'a', 'value' => true]]);

    $this->artisan('flag:sync', ['--path' => $this->path])->assertExitCode(0);
    $this->artisan('flag:sync', ['--path' => $this->path])
        ->expectsOutputToContain('0 created, 0 updated, 0 deleted, 1 unchanged')
        ->assertExitCode(0);
});

it('updates a flag whose value changed in the file', function (): void {
    writeDefs($this->path, [['key' => 'a', 'value' => false]]);
    $this->artisan('flag:sync', ['--path' => $this->path])->assertExitCode(0);

    writeDefs($this->path, [['key' => 'a', 'value' => true, 'hint' => 'flipped']]);
    $this->artisan('flag:sync', ['--path' => $this->path])
        ->expectsOutputToContain('0 created, 1 updated')
        ->assertExitCode(0);

    expect($this->manager->isEnabled('a'))->toBeTrue();
});

it('dry-run writes nothing', function (): void {
    writeDefs($this->path, [['key' => 'a', 'value' => true]]);

    $this->artisan('flag:sync', ['--path' => $this->path, '--dry-run' => true])->assertExitCode(0);

    expect($this->manager->distinctKeys())->toBe([]);
});

it('prune removes flags not in the file but keeps defined ones', function (): void {
    // 'keep' already exists AND is in the file -> prune must not delete it.
    $this->manager->create(['key' => 'keep', 'scope_id' => null, 'value' => true]);
    $this->manager->create(['key' => 'extra', 'scope_id' => null, 'value' => true]);
    writeDefs($this->path, [['key' => 'keep', 'value' => true], ['key' => 'a', 'value' => true]]);

    $this->artisan('flag:sync', ['--path' => $this->path, '--prune' => true])->assertExitCode(0);

    expect($this->manager->isEnabled('a'))->toBeTrue()
        ->and($this->manager->distinctKeys())->toContain('keep')
        ->and($this->manager->distinctKeys())->not->toContain('extra');
});

it('without prune, extra flags are left alone', function (): void {
    $this->manager->create(['key' => 'extra', 'scope_id' => null, 'value' => true]);
    writeDefs($this->path, [['key' => 'a', 'value' => true]]);

    $this->artisan('flag:sync', ['--path' => $this->path])->assertExitCode(0);

    expect($this->manager->distinctKeys())->toContain('extra')->toContain('a');
});

<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;

beforeEach(function (): void {
    $this->manager = app(FeatureFlagManager::class);
    $this->path = sys_get_temp_dir().'/ff_enum_'.uniqid().'.php';
});

afterEach(function (): void {
    @unlink($this->path);
});

it('generates a valid FeatureKey enum from stored keys', function (): void {
    $this->manager->create(['key' => 'new-dashboard', 'scope_id' => null, 'value' => true]);
    $this->manager->create(['key' => 'beta.billing', 'scope_id' => null, 'value' => false]);

    $this->artisan('flag:generate', ['--class' => 'App\\Features\\AppFeature', '--path' => $this->path])
        ->assertExitCode(0);

    $contents = (string) file_get_contents($this->path);

    expect($contents)->toContain('namespace App\\Features;')
        ->toContain('enum AppFeature: string implements FeatureKey')
        ->toContain("case NewDashboard = 'new-dashboard';")
        ->toContain("case BetaBilling = 'beta.billing';")
        ->toContain('public function key(): string');
});

it('refuses to overwrite without --force', function (): void {
    file_put_contents($this->path, 'existing');

    $this->artisan('flag:generate', ['--class' => 'App\\Features\\AppFeature', '--path' => $this->path])
        ->assertExitCode(1);

    expect(file_get_contents($this->path))->toBe('existing');
});

it('overwrites with --force', function (): void {
    file_put_contents($this->path, 'existing');
    $this->manager->create(['key' => 'x', 'scope_id' => null, 'value' => true]);

    $this->artisan('flag:generate', ['--class' => 'App\\Features\\AppFeature', '--path' => $this->path, '--force' => true])
        ->assertExitCode(0);

    expect(file_get_contents($this->path))->toContain("case X = 'x';");
});

it('produces unique case names on collision', function (): void {
    $this->manager->create(['key' => 'new-dashboard', 'scope_id' => null, 'value' => true]);
    $this->manager->create(['key' => 'new.dashboard', 'scope_id' => 'team', 'value' => true]);

    $this->artisan('flag:generate', ['--class' => 'App\\Features\\AppFeature', '--path' => $this->path])
        ->assertExitCode(0);

    $contents = (string) file_get_contents($this->path);

    expect($contents)->toContain('case NewDashboard =')
        ->toContain('case NewDashboard2 =');
});

it('creates the target directory when it does not exist', function (): void {
    $nested = sys_get_temp_dir().'/ff_gen_'.uniqid().'/deep/AppFeature.php';
    $this->manager->create(['key' => 'x', 'scope_id' => null, 'value' => true]);

    $this->artisan('flag:generate', ['--class' => 'App\\Features\\AppFeature', '--path' => $nested])
        ->assertExitCode(0);

    expect(is_file($nested))->toBeTrue();

    @unlink($nested);
    @rmdir(dirname($nested));
    @rmdir(dirname($nested, 2));
});

it('prefixes a case name that would not start with a letter', function (): void {
    $this->manager->create(['key' => '123-go', 'scope_id' => null, 'value' => true]);

    $this->artisan('flag:generate', ['--class' => 'App\\Features\\AppFeature', '--path' => $this->path])
        ->assertExitCode(0);

    expect((string) file_get_contents($this->path))->toContain("case Flag123Go = '123-go';");
});

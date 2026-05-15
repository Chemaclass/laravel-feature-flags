<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'tenant_id' => 'tenant-A',
            ],
        );

        $manager = app(FeatureFlagManager::class);

        $manager->updateOrCreate(
            ['key' => 'new-dashboard', 'scope_id' => null],
            ['value' => true, 'hint' => 'Global new dashboard rollout'],
        );

        $manager->updateOrCreate(
            ['key' => 'beta-billing', 'scope_id' => null],
            ['value' => false, 'hint' => 'Beta billing flow', 'is_dev' => true],
        );

        $manager->updateOrCreate(
            ['key' => 'beta-billing', 'scope_id' => 'tenant-A'],
            ['value' => true, 'hint' => 'Enabled for tenant-A early access'],
        );

        $manager->updateOrCreate(
            ['key' => 'holiday-banner', 'scope_id' => null],
            [
                'value' => true,
                'hint' => 'Time-windowed banner',
                'enabled_from' => now()->subDay(),
                'enabled_until' => now()->addDays(14),
            ],
        );
    }
}

<?php

declare(strict_types=1);
use Chemaclass\FeatureFlags\FeatureFlagsServiceProvider;
use Workbench\App\Providers\WorkbenchServiceProvider;

return [
    WorkbenchServiceProvider::class,
    FeatureFlagsServiceProvider::class,
];

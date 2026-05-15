<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Http\Controllers\FeatureFlagController;
use Illuminate\Support\Facades\Route;

$prefix = (string) config('feature-flags.admin.prefix', 'admin/feature-flags');
$middleware = (array) config('feature-flags.admin.middleware', ['web']);
$name = (string) config('feature-flags.admin.route_name', 'feature-flags.');

Route::prefix($prefix)
    ->middleware($middleware)
    ->name($name)
    ->group(function (): void {
        Route::get('/', [FeatureFlagController::class, 'index'])->name('index');
        Route::post('/', [FeatureFlagController::class, 'store'])->name('store');
        Route::patch('{id}', [FeatureFlagController::class, 'update'])->name('update');
        Route::post('{id}/toggle', [FeatureFlagController::class, 'toggle'])->name('toggle');
        Route::post('{id}/toggle-dev', [FeatureFlagController::class, 'toggleDev'])->name('toggle-dev-row');
        Route::post('toggle-dev/{key}', [FeatureFlagController::class, 'toggleDevByKey'])->name('toggle-dev');
        Route::delete('{id}', [FeatureFlagController::class, 'destroy'])->name('destroy');
    });

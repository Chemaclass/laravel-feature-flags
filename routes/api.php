<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Http\Controllers\FeatureFlagApiController;
use Illuminate\Support\Facades\Route;

/** @var list<string> $middleware */
$middleware = (array) config('feature-flags.api.middleware', ['api']);

Route::prefix((string) config('feature-flags.api.prefix', 'feature-flags/api'))
    ->middleware($middleware)
    ->group(function (): void {
        Route::match(['get', 'post'], 'evaluate', [FeatureFlagApiController::class, 'evaluate'])
            ->name('feature-flags.api.evaluate');
    });

<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\FeatureFlagsServiceProvider;

it('publishes config under feature-flags-config tag', function (): void {
    $paths = FeatureFlagsServiceProvider::pathsToPublish(FeatureFlagsServiceProvider::class, 'feature-flags-config');

    expect($paths)->not->toBeEmpty();
});

it('publishes migrations under feature-flags-migrations tag', function (): void {
    $paths = FeatureFlagsServiceProvider::pathsToPublish(FeatureFlagsServiceProvider::class, 'feature-flags-migrations');

    expect($paths)->not->toBeEmpty();
});

it('publishes views under feature-flags-views tag', function (): void {
    $paths = FeatureFlagsServiceProvider::pathsToPublish(FeatureFlagsServiceProvider::class, 'feature-flags-views');

    expect($paths)->not->toBeEmpty();
});

it('publishes routes under feature-flags-routes tag', function (): void {
    $paths = FeatureFlagsServiceProvider::pathsToPublish(FeatureFlagsServiceProvider::class, 'feature-flags-routes');

    expect($paths)->not->toBeEmpty();
});

it('registers the feature.enabled middleware alias', function (): void {
    $router = app('router');

    expect($router->getMiddleware())->toHaveKey('feature.enabled');
});

it('registers all admin routes by default', function (): void {
    expect(\Route::has('feature-flags.index'))->toBeTrue()
        ->and(\Route::has('feature-flags.store'))->toBeTrue()
        ->and(\Route::has('feature-flags.toggle'))->toBeTrue()
        ->and(\Route::has('feature-flags.toggle-dev'))->toBeTrue()
        ->and(\Route::has('feature-flags.destroy'))->toBeTrue();
});

<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags;

use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Http\Middleware\EnsureFeatureIsActive;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;
use Chemaclass\FeatureFlags\Resolvers\UserTenantScopeResolver;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class FeatureFlagsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/feature-flags.php', 'feature-flags');

        $this->app->singleton(FeatureFlagRepository::class, EloquentFeatureFlagRepository::class);

        $this->app->singleton(FeatureScopeResolver::class, function ($app) {
            /** @var class-string<FeatureScopeResolver> $cls */
            $cls = config('feature-flags.scope.resolver', UserTenantScopeResolver::class);

            return $app->make($cls);
        });

        $this->app->singleton(FeatureFlagManager::class);
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'feature-flags');

        $alias = (string) config('feature-flags.middleware_alias', 'feature.enabled');
        $router->aliasMiddleware($alias, EnsureFeatureIsActive::class);

        if ((bool) config('feature-flags.admin.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        }

        $this->publishes([
            __DIR__.'/../config/feature-flags.php' => config_path('feature-flags.php'),
        ], 'feature-flags-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'feature-flags-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/feature-flags'),
        ], 'feature-flags-views');

        $this->publishes([
            __DIR__.'/../routes/admin.php' => base_path('routes/feature-flags.php'),
        ], 'feature-flags-routes');
    }
}

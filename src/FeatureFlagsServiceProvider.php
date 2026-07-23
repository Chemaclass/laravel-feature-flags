<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags;

use Chemaclass\FeatureFlags\Blade\FeatureDirective;
use Chemaclass\FeatureFlags\Console\CreateFlagCommand;
use Chemaclass\FeatureFlags\Console\DeleteFlagCommand;
use Chemaclass\FeatureFlags\Console\ListFlagsCommand;
use Chemaclass\FeatureFlags\Console\ToggleFlagCommand;
use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\Contracts\FeatureKey;
use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Http\Middleware\EnsureFeatureIsActive;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Repository\CachingFeatureFlagRepository;
use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;
use Chemaclass\FeatureFlags\Resolvers\NullScopeResolver;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

final class FeatureFlagsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/feature-flags.php', 'feature-flags');

        $this->app->singleton(FeatureFlagRepository::class, function ($app): FeatureFlagRepository {
            /** @var array{store?: ?string, ttl?: int, prefix?: string} $cache */
            $cache = config('feature-flags.cache', []);

            return new CachingFeatureFlagRepository(
                $app->make(EloquentFeatureFlagRepository::class),
                $app->make(CacheFactory::class),
                $cache,
            );
        });

        $this->app->singleton(FeatureScopeResolver::class, function ($app) {
            /** @var class-string<FeatureScopeResolver> $cls */
            $cls = config('feature-flags.scope.resolver', NullScopeResolver::class);

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

        // @feature('key') ... @endfeature (plus @unlessfeature / @elsefeature).
        Blade::if('feature', fn (FeatureKey|string $flag, ?string $scopeId = null): bool => $this->app->make(FeatureDirective::class)->isEnabled($flag, $scopeId));

        if ((bool) config('feature-flags.admin.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListFlagsCommand::class,
                ToggleFlagCommand::class,
                CreateFlagCommand::class,
                DeleteFlagCommand::class,
            ]);
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

        $this->publishes([
            __DIR__.'/../config/feature-flags.php' => config_path('feature-flags.php'),
            __DIR__.'/../database/migrations' => database_path('migrations'),
            __DIR__.'/../resources/views' => resource_path('views/vendor/feature-flags'),
            __DIR__.'/../routes/admin.php' => base_path('routes/feature-flags.php'),
        ], 'feature-flags');
    }
}

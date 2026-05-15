<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Http\Middleware\AutoLoginDemo;

final class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config()->set('feature-flags.admin.middleware', [
            'web',
            AutoLoginDemo::class,
        ]);
    }

    public function boot(Router $router): void
    {
        $router->pushMiddlewareToGroup('web', AutoLoginDemo::class);
    }
}

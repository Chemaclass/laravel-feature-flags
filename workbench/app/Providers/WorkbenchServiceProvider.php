<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

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
}

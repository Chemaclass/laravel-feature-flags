<?php

declare(strict_types=1);

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Workbench\App\Models\User;

final class AutoLoginDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            $user = User::query()->where('email', 'demo@example.com')->first();
            if ($user !== null) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

Route::get('/', function () {
    $user = User::query()->where('email', 'demo@example.com')->first();
    if ($user !== null) {
        Auth::login($user);
    }

    return redirect('/admin/feature-flags');
});

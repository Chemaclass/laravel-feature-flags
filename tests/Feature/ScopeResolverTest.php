<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Http\Middleware\EnsureFeatureIsActive;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Resolvers\NullScopeResolver;
use Chemaclass\FeatureFlags\Resolvers\UserTenantScopeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

it('NullScopeResolver always returns null', function (): void {
    $resolver = new NullScopeResolver();

    expect($resolver->resolve(Request::create('/')))->toBeNull();
});

it('UserTenantScopeResolver reads $user->tenant_id', function (): void {
    $user = User::query()->create([
        'name'      => 'Scoped',
        'email'     => 'scoped@example.com',
        'password'  => bcrypt('x'),
        'tenant_id' => 'tenant-Z',
    ]);

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    expect((new UserTenantScopeResolver())->resolve($request))->toBe('tenant-Z');
});

it('UserTenantScopeResolver returns null when user is anonymous', function (): void {
    expect((new UserTenantScopeResolver())->resolve(Request::create('/')))->toBeNull();
});

it('middleware uses the bound resolver to pick scope', function (): void {
    // Custom resolver: always returns "scope-X"
    $this->app->instance(FeatureScopeResolver::class, new class implements FeatureScopeResolver {
        public function resolve(Request $request): ?string
        {
            return 'scope-X';
        }
    });

    $manager = app(FeatureFlagManager::class);
    $manager->create(['key' => 'gated', 'scope_id' => null,      'value' => false]);
    $manager->create(['key' => 'gated', 'scope_id' => 'scope-X', 'value' => true]);

    Route::get('/probe', fn () => 'ok')
        ->middleware(EnsureFeatureIsActive::using('gated'));

    $this->get('/probe')->assertOk();
});

it('middleware blocks when bound resolver picks a scope without an override', function (): void {
    $this->app->instance(FeatureScopeResolver::class, new class implements FeatureScopeResolver {
        public function resolve(Request $request): ?string
        {
            return 'scope-Y';
        }
    });

    app(FeatureFlagManager::class)
        ->create(['key' => 'gated2', 'scope_id' => null, 'value' => false]);

    Route::get('/probe2', fn () => 'ok')
        ->middleware(EnsureFeatureIsActive::using('gated2'));

    $this->get('/probe2')->assertStatus(400);
});

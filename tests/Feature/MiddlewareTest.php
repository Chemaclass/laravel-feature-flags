<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Contracts\FeatureKey;
use Chemaclass\FeatureFlags\Http\Middleware\EnsureFeatureIsActive;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Support\Facades\Route;

enum MwFeature: string implements FeatureKey
{
    case ScopedThing = 'scoped-thing';

    public function key(): string
    {
        return $this->value;
    }
}

beforeEach(function (): void {
    Route::get('/probe-static', fn () => response('ok'))
        ->middleware(EnsureFeatureIsActive::using(MwFeature::ScopedThing));

    Route::get('/probe-alias', fn () => response('ok'))
        ->middleware('feature.enabled:scoped-thing');
});

it('allows request when flag globally enabled (static helper)', function (): void {
    app(FeatureFlagManager::class)->create([
        'key' => 'scoped-thing', 'scope_id' => null, 'value' => true,
    ]);

    $this->get('/probe-static')->assertOk()->assertSee('ok');
});

it('blocks request when flag missing', function (): void {
    $this->get('/probe-static')
        ->assertStatus(400)
        ->assertJson(['message' => 'Feature disabled']);
});

it('blocks request when flag globally disabled', function (): void {
    app(FeatureFlagManager::class)->create([
        'key' => 'scoped-thing', 'scope_id' => null, 'value' => false,
    ]);

    $this->get('/probe-static')
        ->assertStatus(400)
        ->assertJson(['message' => 'Feature disabled']);
});

it('alias middleware works identically', function (): void {
    app(FeatureFlagManager::class)->create([
        'key' => 'scoped-thing', 'scope_id' => null, 'value' => true,
    ]);

    $this->get('/probe-alias')->assertOk();
});

it('static helper accepts a raw string key', function (): void {
    Route::get('/probe-string', fn () => 'ok')
        ->middleware(EnsureFeatureIsActive::using('plain-string-key'));

    $this->get('/probe-string')->assertStatus(400);

    app(FeatureFlagManager::class)->create([
        'key' => 'plain-string-key', 'scope_id' => null, 'value' => true,
    ]);

    $this->get('/probe-string')->assertOk();
});

it('respects time-window: blocked before enabled_from', function (): void {
    app(FeatureFlagManager::class)->create([
        'key' => 'scoped-thing',
        'scope_id' => null,
        'value' => true,
        'enabled_from' => now()->addDay(),
    ]);

    $this->get('/probe-static')->assertStatus(400);
});

it('respects time-window: blocked after enabled_until', function (): void {
    app(FeatureFlagManager::class)->create([
        'key' => 'scoped-thing',
        'scope_id' => null,
        'value' => true,
        'enabled_until' => now()->subDay(),
    ]);

    $this->get('/probe-static')->assertStatus(400);
});

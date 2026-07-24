<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Http\Request;

beforeEach(function (): void {
    config()->set('feature-flags.api.enabled', true);
    config()->set('feature-flags.api.middleware', []); // no auth/throttle in tests

    // The provider booted with the API disabled; register the routes now.
    require __DIR__.'/../../routes/api.php';

    $this->manager = app(FeatureFlagManager::class);
});

it('evaluates every flag when no keys are given', function (): void {
    $this->manager->create(['key' => 'a', 'scope_id' => null, 'value' => true]);
    $this->manager->create(['key' => 'b', 'scope_id' => null, 'value' => false]);

    $this->postJson('/feature-flags/api/evaluate')
        ->assertOk()
        ->assertJsonPath('flags.a', true)
        ->assertJsonPath('flags.b', false)
        ->assertJsonPath('variants.a', null);
});

it('evaluates only the requested keys', function (): void {
    $this->manager->create(['key' => 'a', 'scope_id' => null, 'value' => true]);
    $this->manager->create(['key' => 'b', 'scope_id' => null, 'value' => true]);

    $this->postJson('/feature-flags/api/evaluate', ['keys' => ['a']])
        ->assertOk()
        ->assertJsonPath('flags.a', true)
        ->assertJsonMissingPath('flags.b');
});

it('applies the scope from the request', function (): void {
    $this->manager->create(['key' => 'a', 'scope_id' => null, 'value' => false]);
    $this->manager->create(['key' => 'a', 'scope_id' => 'team-1', 'value' => true]);

    $this->postJson('/feature-flags/api/evaluate', ['scope' => 'team-1', 'keys' => ['a']])
        ->assertOk()
        ->assertJsonPath('scope', 'team-1')
        ->assertJsonPath('flags.a', true);
});

it('applies targeting rules from the context', function (): void {
    $this->manager->create([
        'key' => 'a', 'scope_id' => null, 'value' => false,
        'rules' => [['when' => [['attr' => 'plan', 'op' => 'eq', 'value' => 'pro']], 'then' => true]],
    ]);

    $this->postJson('/feature-flags/api/evaluate', ['keys' => ['a'], 'context' => ['plan' => 'pro']])
        ->assertOk()
        ->assertJsonPath('flags.a', true);
});

it('returns the selected variant with its payload', function (): void {
    $this->manager->create([
        'key' => 'home', 'scope_id' => null, 'value' => true,
        'variants' => [['name' => 'blue', 'weight' => 100]],
        'variant_payloads' => ['blue' => ['cta' => 'Go']],
    ]);

    $this->postJson('/feature-flags/api/evaluate', ['keys' => ['home']])
        ->assertOk()
        ->assertJsonPath('variants.home.name', 'blue')
        ->assertJsonPath('variants.home.payload.cta', 'Go');
});

it('falls back to the bound scope resolver when no scope is given', function (): void {
    $this->app->instance(FeatureScopeResolver::class, new class implements FeatureScopeResolver
    {
        public function resolve(Request $request): ?string
        {
            return 'resolved-scope';
        }
    });
    $this->manager->create(['key' => 'a', 'scope_id' => null, 'value' => false]);
    $this->manager->create(['key' => 'a', 'scope_id' => 'resolved-scope', 'value' => true]);

    $this->postJson('/feature-flags/api/evaluate', ['keys' => ['a']])
        ->assertOk()
        ->assertJsonPath('scope', 'resolved-scope')
        ->assertJsonPath('flags.a', true);
});

it('validates the payload shape', function (): void {
    $this->postJson('/feature-flags/api/evaluate', ['keys' => 'not-an-array'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('keys');
});

it('responds on GET too', function (): void {
    $this->manager->create(['key' => 'a', 'scope_id' => null, 'value' => true]);

    $this->getJson('/feature-flags/api/evaluate')
        ->assertOk()
        ->assertJsonPath('flags.a', true);
});

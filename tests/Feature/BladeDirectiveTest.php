<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

beforeEach(function (): void {
    $this->manager = app(FeatureFlagManager::class);
});

function render(string $template): string
{
    return trim(Blade::render($template));
}

it('@feature renders the block when the flag is enabled', function (): void {
    $this->manager->create(['key' => 'promo', 'scope_id' => null, 'value' => true]);

    expect(render("@feature('promo') ON @endfeature"))->toBe('ON');
});

it('@feature hides the block when the flag is disabled', function (): void {
    $this->manager->create(['key' => 'promo', 'scope_id' => null, 'value' => false]);

    expect(render("@feature('promo') ON @endfeature"))->toBe('');
});

it('@feature ... @else renders the else branch when disabled', function (): void {
    $this->manager->create(['key' => 'promo', 'scope_id' => null, 'value' => false]);

    expect(render("@feature('promo') ON @else OFF @endfeature"))->toBe('OFF');
});

it('@unlessfeature renders when the flag is disabled', function (): void {
    $this->manager->create(['key' => 'promo', 'scope_id' => null, 'value' => false]);

    expect(render("@unlessfeature('promo') FALLBACK @endfeature"))->toBe('FALLBACK');
});

it('@feature resolves scope through the bound resolver (matches middleware)', function (): void {
    $this->app->instance(FeatureScopeResolver::class, new class implements FeatureScopeResolver
    {
        public function resolve(Request $request): ?string
        {
            return 'scope-X';
        }
    });

    $this->manager->create(['key' => 'gated', 'scope_id' => null, 'value' => false]);
    $this->manager->create(['key' => 'gated', 'scope_id' => 'scope-X', 'value' => true]);

    expect(render("@feature('gated') ON @endfeature"))->toBe('ON');
});

it('@feature accepts an explicit scope id that overrides the resolver', function (): void {
    $this->manager->create(['key' => 'gated', 'scope_id' => null, 'value' => false]);
    $this->manager->create(['key' => 'gated', 'scope_id' => 'team-9', 'value' => true]);

    expect(render("@feature('gated', 'team-9') ON @endfeature"))->toBe('ON');
});

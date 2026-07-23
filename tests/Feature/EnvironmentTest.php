<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;

beforeEach(function (): void {
    $this->repo = app(EloquentFeatureFlagRepository::class);
    config()->set('feature-flags.environment.current', 'production');
});

it('env-null rows apply to every environment (back-compat)', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'environment' => null, 'value' => true]);

    expect($this->repo->isEnabled('k'))->toBeTrue();

    config()->set('feature-flags.environment.current', 'staging');
    expect($this->repo->isEnabled('k'))->toBeTrue();
});

it('an env-specific row wins over the env-null row in that environment', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'environment' => null, 'value' => true]);
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'environment' => 'production', 'value' => false]);

    expect($this->repo->isEnabled('k'))->toBeFalse();

    config()->set('feature-flags.environment.current', 'staging');
    // staging has no specific row -> falls back to env-null (true)
    expect($this->repo->isEnabled('k'))->toBeTrue();
});

it('other environments never see an env-specific row', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'environment' => 'production', 'value' => true]);

    config()->set('feature-flags.environment.current', 'staging');
    expect($this->repo->isEnabled('k'))->toBeFalse();
});

it('scope precedence dominates environment precedence', function (): void {
    // rank 2: (scope=team, env=null) true ; rank 3: (scope=null, env=prod) false
    $this->repo->create(['key' => 'k', 'scope_id' => 'team', 'environment' => null, 'value' => true]);
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'environment' => 'production', 'value' => false]);

    // scope row (rank 2) beats the global env-specific row (rank 3)
    expect($this->repo->isEnabled('k', 'team'))->toBeTrue();
});

it('most specific (scope + env) wins overall', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => 'team', 'environment' => 'production', 'value' => true]);
    $this->repo->create(['key' => 'k', 'scope_id' => 'team', 'environment' => null, 'value' => false]);

    expect($this->repo->isEnabled('k', 'team'))->toBeTrue();
});

it('listForScope respects the current environment', function (): void {
    $this->repo->create(['key' => 'a', 'scope_id' => null, 'environment' => null, 'value' => false]);
    $this->repo->create(['key' => 'a', 'scope_id' => null, 'environment' => 'production', 'value' => true]);
    $this->repo->create(['key' => 'b', 'scope_id' => null, 'environment' => 'staging', 'value' => true]);

    $result = $this->repo->listForScope(null);

    expect($result)->toHaveKey('a', true)  // prod-specific wins
        ->not->toHaveKey('b');             // staging-only row excluded in production
});

<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Pennant\FeatureFlagsPennantDriver;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PennantServiceProvider;

beforeEach(function (): void {
    if (! class_exists(Feature::class)) {
        $this->markTestSkipped('laravel/pennant is not installed.');
    }

    $this->app->register(PennantServiceProvider::class);
    config()->set('pennant.stores.feature-flags', ['driver' => 'feature-flags']);
    config()->set('pennant.default', 'feature-flags');

    Feature::extend('feature-flags', fn ($app): FeatureFlagsPennantDriver => $app->make(FeatureFlagsPennantDriver::class));

    $this->manager = app(FeatureFlagManager::class);
});

it('Feature::active resolves through this package (global)', function (): void {
    $this->manager->create(['key' => 'x', 'scope_id' => null, 'value' => true]);

    expect(Feature::active('x'))->toBeTrue()
        ->and(Feature::inactive('x'))->toBeFalse();
});

it('Feature::active is false for a disabled or missing flag', function (): void {
    $this->manager->create(['key' => 'off', 'scope_id' => null, 'value' => false]);

    expect(Feature::active('off'))->toBeFalse()
        ->and(Feature::active('missing'))->toBeFalse();
});

it('Feature::for(scope) maps a string scope to this package scope id', function (): void {
    $this->manager->create(['key' => 'x', 'scope_id' => null, 'value' => false]);
    $this->manager->create(['key' => 'x', 'scope_id' => 'team-1', 'value' => true]);

    expect(Feature::for('team-1')->active('x'))->toBeTrue()
        ->and(Feature::active('x'))->toBeFalse();
});

it('driver writes and deletes through the manager', function (): void {
    $driver = app(FeatureFlagsPennantDriver::class);

    $driver->set('y', 'team-9', true);
    expect($this->manager->isEnabled('y', 'team-9'))->toBeTrue();

    $driver->delete('y', 'team-9');
    expect($this->manager->findByKeyAndScope('y', 'team-9'))->toBeNull();
});

it('define/defined track resolvers', function (): void {
    $driver = app(FeatureFlagsPennantDriver::class);

    $driver->define('a', fn () => true);
    $driver->define('b', fn () => false);

    expect($driver->defined())->toBe(['a', 'b']);
});

it('getAll resolves a matrix of features and scopes', function (): void {
    $driver = app(FeatureFlagsPennantDriver::class);
    $this->manager->create(['key' => 'a', 'scope_id' => null, 'value' => true]);
    $this->manager->create(['key' => 'a', 'scope_id' => 'team-1', 'value' => false]);

    $result = $driver->getAll(['a' => ['team-1', null]]);

    expect($result)->toBe(['a' => [false, true]]);
});

it('setForAllScopes writes the global row', function (): void {
    $driver = app(FeatureFlagsPennantDriver::class);

    $driver->setForAllScopes('z', true);

    expect($this->manager->findByKeyAndScope('z', null)->value)->toBeTrue();
});

it('delete is a no-op when the flag is missing', function (): void {
    $driver = app(FeatureFlagsPennantDriver::class);

    $driver->delete('missing', 'team-9'); // must not throw

    expect($this->manager->findByKeyAndScope('missing', 'team-9'))->toBeNull();
});

it('purge deletes given global features and ignores null', function (): void {
    $driver = app(FeatureFlagsPennantDriver::class);
    $this->manager->create(['key' => 'p', 'scope_id' => null, 'value' => true]);
    $this->manager->create(['key' => 'keep', 'scope_id' => null, 'value' => true]);

    $driver->purge(null); // no-op branch
    expect($this->manager->findByKeyAndScope('p', null))->not->toBeNull();

    $driver->purge(['p', 'not-there']);

    expect($this->manager->findByKeyAndScope('p', null))->toBeNull()
        ->and($this->manager->findByKeyAndScope('keep', null))->not->toBeNull();
});

it('maps every scope shape to a scope id', function (): void {
    $driver = app(FeatureFlagsPennantDriver::class);
    $this->manager->create(['key' => 'f', 'scope_id' => null, 'value' => false]);
    $this->manager->create(['key' => 'f', 'scope_id' => '7', 'value' => true]);        // model key & scalar
    $this->manager->create(['key' => 'f', 'scope_id' => 'ident-9', 'value' => true]);  // FeatureScopeable

    $model = new class extends Model
    {
        public function getKey()
        {
            return 7;
        }
    };
    $scopeable = new class implements FeatureScopeable
    {
        public function toFeatureIdentifier($driver): mixed
        {
            return 'ident-9';
        }
    };

    expect($driver->get('f', null))->toBeFalse()      // null scope
        ->and($driver->get('f', 7))->toBeTrue()        // scalar
        ->and($driver->get('f', $model))->toBeTrue()   // Eloquent model -> key 7
        ->and($driver->get('f', $scopeable))->toBeTrue(); // FeatureScopeable
});

it('maps a non-scalar, non-model scope to null', function (): void {
    $driver = app(FeatureFlagsPennantDriver::class);
    $this->manager->create(['key' => 'g', 'scope_id' => null, 'value' => true]);

    // an array scope is neither string/scalar/model/scopeable -> null -> global row
    expect($driver->get('g', ['x']))->toBeTrue();
});

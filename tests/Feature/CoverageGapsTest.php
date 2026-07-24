<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\Contracts\FeatureKey;
use Chemaclass\FeatureFlags\Events\FlagsChanged;
use Chemaclass\FeatureFlags\Listeners\InvalidateFlagCache;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;
use Chemaclass\FeatureFlags\Resolvers\UserTenantScopeResolver;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Request;

enum CoverageFeature: string implements FeatureKey
{
    case Alpha = 'cov-alpha';

    public function key(): string
    {
        return $this->value;
    }
}

beforeEach(function (): void {
    $this->manager = app(FeatureFlagManager::class);
    $this->repo = app(EloquentFeatureFlagRepository::class);
});

it('manager allEnabled accepts FeatureKey enums', function (): void {
    $this->manager->create(['key' => 'cov-alpha', 'scope_id' => null, 'value' => true]);

    expect($this->manager->allEnabled([CoverageFeature::Alpha, 'other']))
        ->toBe(['cov-alpha' => true, 'other' => false]);
});

it('allEnabled short-circuits on an empty key list', function (): void {
    expect($this->repo->allEnabled([]))->toBe([]);
});

it('variant returns null when no variant can be selected', function (): void {
    // Enabled flag, but every variant has zero weight -> selector yields null.
    $this->repo->create([
        'key' => 'z', 'scope_id' => null, 'value' => true,
        'variants' => [['name' => 'a', 'weight' => 0]],
    ]);

    expect($this->manager->variant('z'))->toBeNull();
});

it('listForScope is memoized within a request', function (): void {
    $repo = app(FeatureFlagRepository::class);
    $repo->create(['key' => 'a', 'scope_id' => null, 'value' => true]);

    $first = $repo->listForScope(null);
    $second = $repo->listForScope(null); // memo hit

    expect($second)->toBe($first);
});

it('InvalidateFlagCache is a no-op when no cache store is configured', function (): void {
    config()->set('feature-flags.cache', ['store' => null, 'prefix' => 'ff-none']);

    app(InvalidateFlagCache::class)->handle(new FlagsChanged); // must not throw

    expect(app(CacheFactory::class)->store('array')->get('ff-none:version'))->toBeNull();
});

it('UserTenantScopeResolver reads a tenant object with an id', function (): void {
    $user = new class
    {
        public object $tenant;

        public function __construct()
        {
            $this->tenant = (object) ['id' => 'tenant-obj-9'];
        }
    };

    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    expect((new UserTenantScopeResolver)->resolve($request))->toBe('tenant-obj-9');
});

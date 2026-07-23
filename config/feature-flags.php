<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Models\FeatureFlag;
use Chemaclass\FeatureFlags\Resolvers\NullScopeResolver;

return [

    'table' => 'feature_flags',

    'model' => FeatureFlag::class,

    'scope' => [
        'column' => 'scope_id',
        'resolver' => NullScopeResolver::class,
    ],

    'admin' => [
        'enabled' => true,
        'prefix' => 'admin/feature-flags',
        'middleware' => ['web', 'auth'],
        'route_name' => 'feature-flags.',
    ],

    'middleware_alias' => 'feature.enabled',

    /*
     * Evaluation cache. Flag checks are always memoized per request. Set a
     * `store` (any cache store name from config/cache.php) to also cache
     * evaluations across requests; writes bump a namespace version so a single
     * change invalidates every cached evaluation. `null` = memoization only.
     */
    'cache' => [
        'store' => env('FEATURE_FLAGS_CACHE_STORE'),
        'ttl' => 60,
        'prefix' => 'feature-flags',
    ],
];

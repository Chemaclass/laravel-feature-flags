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
     * Config-as-code. `flag:sync` reconciles the definitions file below into the
     * database (upsert; --prune removes flags not in the file).
     */
    'sync' => [
        'path' => base_path('feature-flags.php'),
    ],

    /*
     * Global kill switch. Any key listed here is forced off before any query,
     * regardless of its stored value — a master off-switch for incidents.
     */
    'kill_switch' => array_filter(explode(',', (string) env('FEATURE_FLAGS_KILL_SWITCH', ''))),

    /*
     * Per-environment flags. A row's `environment` column scopes it to one
     * environment; null applies to all. By default the current environment is
     * `app()->environment()`; set `current` to override (e.g. for testing).
     */
    'environment' => [
        'current' => env('FEATURE_FLAGS_ENVIRONMENT'),
    ],

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

    'events' => [
        /*
         * Dispatch a FlagEvaluated event on every flag check. Off by default to
         * keep the hot path free; turn on for debugging or audit trails.
         * FlagToggled (value flips) is always dispatched regardless of this.
         */
        'evaluation' => env('FEATURE_FLAGS_EVENTS_EVALUATION', false),
    ],

    /*
     * Optional Laravel Pennant bridge. When enabled (and laravel/pennant is
     * installed), registers a Pennant driver named below that resolves through
     * this package. Point pennant.default at it to have Feature::active() read
     * from here. No effect when Pennant is absent.
     */
    'pennant' => [
        'enabled' => env('FEATURE_FLAGS_PENNANT_ENABLED', false),
        'driver' => 'feature-flags',
    ],
];

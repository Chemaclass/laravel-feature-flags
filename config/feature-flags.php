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
     * JSON HTTP API for frontends and other services. Off by default — enabling
     * it exposes flag evaluations over HTTP, so choose middleware to match how
     * public the data is (auth, throttle, signed, a token guard, …). The bundled
     * JS client (feature-flags-js publish tag) talks to `POST {prefix}/evaluate`.
     */
    'api' => [
        'enabled' => env('FEATURE_FLAGS_API_ENABLED', false),
        'prefix' => 'feature-flags/api',
        'middleware' => ['api'],
    ],

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

    /*
     * Audit log. When enabled, a listener records every flag toggle to the
     * audits table. `actor` is an optional class with resolve(): ?string; the
     * default is the authenticated user id.
     */
    'audit' => [
        'enabled' => env('FEATURE_FLAGS_AUDIT_ENABLED', false),
        'table' => 'feature_flag_audits',
        'actor' => null,
    ],

    /*
     * Real-time cache invalidation. When enabled, every flag write broadcasts a
     * FlagsChanged event; a listener bumps the cache namespace version on each
     * node so a change propagates instantly instead of waiting for the TTL.
     * Only useful with a cache store configured above.
     */
    'realtime' => [
        'enabled' => env('FEATURE_FLAGS_REALTIME_ENABLED', false),
        'connection' => env('FEATURE_FLAGS_REALTIME_CONNECTION'),
        'channel' => 'feature-flags',
    ],

    /*
     * Exposure analytics. When enabled, each evaluation increments an aggregate
     * counter (per key / variant / result) so you can see how often flags are hit
     * and how variants split — read them with `flag:stats`. Off by default; every
     * recorded exposure is a small DB write, so consider a queue or sampling for
     * very high traffic.
     */
    'analytics' => [
        'enabled' => env('FEATURE_FLAGS_ANALYTICS_ENABLED', false),
        'table' => 'feature_flag_exposures',
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

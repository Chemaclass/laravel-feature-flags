<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Models\FeatureFlag;
use Chemaclass\FeatureFlags\Resolvers\UserTenantScopeResolver;

return [

    'table' => 'feature_flags',

    'model' => FeatureFlag::class,

    'scope' => [
        'column'   => 'scope_id',
        'resolver' => UserTenantScopeResolver::class,
    ],

    'admin' => [
        'enabled'    => true,
        'prefix'     => 'admin/feature-flags',
        'middleware' => ['web', 'auth'],
        'route_name' => 'feature-flags.',
    ],

    'middleware_alias' => 'feature.enabled',
];

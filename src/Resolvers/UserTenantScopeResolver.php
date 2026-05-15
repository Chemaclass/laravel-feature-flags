<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Resolvers;

use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Illuminate\Http\Request;

final class UserTenantScopeResolver implements FeatureScopeResolver
{
    public function resolve(Request $request): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $tenant = $user->tenant ?? null;
        if (is_object($tenant) && isset($tenant->id)) {
            return (string) $tenant->id;
        }

        $tenantId = $user->tenant_id ?? null;

        return $tenantId !== null ? (string) $tenantId : null;
    }
}

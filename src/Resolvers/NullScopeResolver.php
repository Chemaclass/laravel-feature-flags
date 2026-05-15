<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Resolvers;

use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Illuminate\Http\Request;

final class NullScopeResolver implements FeatureScopeResolver
{
    public function resolve(Request $request): ?string
    {
        return null;
    }
}

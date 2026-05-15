<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Contracts;

use Illuminate\Http\Request;

interface FeatureScopeResolver
{
    public function resolve(Request $request): ?string;
}

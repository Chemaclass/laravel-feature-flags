<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Blade;

use Chemaclass\FeatureFlags\Contracts\FeatureKey;
use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;

/**
 * Backs the `@feature` / `@unlessfeature` Blade conditionals. When no scope is
 * given it resolves the current scope through the bound FeatureScopeResolver,
 * so template checks match the middleware.
 */
final readonly class FeatureDirective
{
    public function __construct(
        private FeatureFlagManager $manager,
        private FeatureScopeResolver $scopeResolver,
    ) {}

    public function isEnabled(FeatureKey|string $flag, ?string $scopeId = null): bool
    {
        $scopeId ??= $this->scopeResolver->resolve(request());

        return $this->manager->isEnabled($flag, $scopeId);
    }
}

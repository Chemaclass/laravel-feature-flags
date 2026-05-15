<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Http\Middleware;

use Chemaclass\FeatureFlags\Contracts\FeatureKey;
use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureFeatureIsActive
{
    public function __construct(
        private FeatureFlagManager $manager,
        private FeatureScopeResolver $scopeResolver,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $scopeId = $this->scopeResolver->resolve($request);

        if (! $this->manager->isEnabled($feature, $scopeId)) {
            return response()->json(['message' => 'Feature disabled'], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }

    public static function using(FeatureKey|string $feature): string
    {
        $key = $feature instanceof FeatureKey ? $feature->key() : $feature;

        return self::class.':'.$key;
    }
}

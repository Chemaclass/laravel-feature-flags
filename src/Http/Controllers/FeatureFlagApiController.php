<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Http\Controllers;

use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * JSON API so non-PHP frontends and other services can evaluate flags. Reads
 * through FeatureFlagManager (never Eloquent directly) so rules, rollout,
 * environments, prerequisites and the kill-switch all apply.
 */
final class FeatureFlagApiController extends Controller
{
    public function __construct(
        private readonly FeatureFlagManager $manager,
        private readonly FeatureScopeResolver $scopeResolver,
    ) {}

    public function evaluate(Request $request): JsonResponse
    {
        /** @var array{keys?: list<string>, scope?: ?string, context?: array<string, mixed>} $data */
        $data = $request->validate([
            'keys' => ['sometimes', 'array'],
            'keys.*' => ['string'],
            'scope' => ['sometimes', 'nullable', 'string'],
            'context' => ['sometimes', 'array'],
        ]);

        $scope = array_key_exists('scope', $data)
            ? $data['scope']
            : $this->scopeResolver->resolve($request);
        $context = $data['context'] ?? [];
        $keys = $data['keys'] ?? $this->manager->distinctKeys();

        $flags = $this->manager->allEnabled($keys, $scope, $context);

        $variants = [];
        foreach ($keys as $key) {
            $variant = $this->manager->variant($key, $scope, $context);
            $variants[$key] = $variant === null
                ? null
                : ['name' => $variant->name, 'payload' => $variant->payload];
        }

        return response()->json([
            'scope' => $scope,
            'flags' => $flags,
            'variants' => $variants,
        ]);
    }
}

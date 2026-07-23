<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Events;

/**
 * A flag was evaluated. Off by default (config `feature-flags.events.evaluation`)
 * to keep the hot path free; enable for debugging or audit trails. When the
 * caching layer is active this fires only on a real evaluation, not a cache hit.
 */
final readonly class FlagEvaluated
{
    public function __construct(
        public string $key,
        public ?string $scopeId,
        public bool $result,
    ) {}
}

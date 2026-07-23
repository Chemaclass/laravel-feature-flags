<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Events;

/**
 * A flag's boolean value was flipped (via toggleValue). Useful for audit logs
 * and reacting to admin-UI toggles.
 */
final readonly class FlagToggled
{
    public function __construct(
        public string $key,
        public ?string $scopeId,
        public bool $oldValue,
        public bool $newValue,
    ) {}
}

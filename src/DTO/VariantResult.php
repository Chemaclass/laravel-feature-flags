<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\DTO;

/**
 * The variant selected for a flag: its name and optional payload.
 */
final readonly class VariantResult
{
    public function __construct(
        public string $name,
        public mixed $payload = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\DTO;

/**
 * Aggregated exposure counts for a single flag key.
 */
final readonly class ExposureStats
{
    /**
     * @param  array<string, int>  $variants  variant name => exposure count
     */
    public function __construct(
        public string $key,
        public int $enabled,
        public int $disabled,
        public array $variants = [],
    ) {}

    public function total(): int
    {
        return $this->enabled + $this->disabled;
    }
}

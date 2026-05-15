<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Contracts;

interface FeatureKey
{
    public function key(): string;
}

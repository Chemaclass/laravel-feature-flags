<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Contracts;

interface AuditActorResolver
{
    /**
     * Identify the actor responsible for a flag change (e.g. a user id), or null.
     */
    public function resolve(): ?string;
}

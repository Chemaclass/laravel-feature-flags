<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Listeners;

use Chemaclass\FeatureFlags\Contracts\AuditActorResolver;
use Chemaclass\FeatureFlags\Events\FlagToggled;
use Chemaclass\FeatureFlags\Models\FeatureFlagAudit;
use Illuminate\Support\Facades\Auth;

/**
 * Persists an audit row on every flag toggle. Registered only when
 * `feature-flags.audit.enabled` is true.
 */
final class RecordFlagChange
{
    public function handle(FlagToggled $event): void
    {
        FeatureFlagAudit::query()->create([
            'key' => $event->key,
            'scope_id' => $event->scopeId,
            'action' => 'toggled',
            'old_value' => $event->oldValue,
            'new_value' => $event->newValue,
            'actor' => $this->resolveActor(),
        ]);
    }

    private function resolveActor(): ?string
    {
        /** @var class-string<AuditActorResolver>|null $resolver */
        $resolver = config('feature-flags.audit.actor');
        if ($resolver !== null) {
            return app($resolver)->resolve();
        }

        $id = Auth::id();

        return $id === null ? null : (string) $id;
    }
}

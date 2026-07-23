<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Broadcast on every flag write when real-time invalidation is enabled, so all
 * nodes bump their cache namespace version and drop stale evaluations.
 */
final class FlagsChanged implements ShouldBroadcast
{
    public function broadcastOn(): Channel
    {
        return new Channel((string) config('feature-flags.realtime.channel', 'feature-flags'));
    }

    public function broadcastConnection(): ?string
    {
        /** @var string|null $connection */
        $connection = config('feature-flags.realtime.connection');

        return $connection;
    }
}

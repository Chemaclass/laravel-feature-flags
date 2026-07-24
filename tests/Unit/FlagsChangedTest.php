<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Events\FlagsChanged;
use Illuminate\Broadcasting\Channel;

it('broadcasts on the configured channel', function (): void {
    config()->set('feature-flags.realtime.channel', 'my-flags');

    $channel = (new FlagsChanged)->broadcastOn();

    expect($channel)->toBeInstanceOf(Channel::class)
        ->and($channel->name)->toBe('my-flags');
});

it('uses the configured broadcast connection', function (): void {
    config()->set('feature-flags.realtime.connection', 'redis');

    expect((new FlagsChanged)->broadcastConnection())->toBe('redis');

    config()->set('feature-flags.realtime.connection', null);
    expect((new FlagsChanged)->broadcastConnection())->toBeNull();
});

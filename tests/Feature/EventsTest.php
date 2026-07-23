<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Events\FlagEvaluated;
use Chemaclass\FeatureFlags\Events\FlagToggled;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Support\Facades\Event;

// The repository is a singleton that injects the event dispatcher, so Event::fake()
// must run before the manager (and thus the repository) is resolved.
function manager(): FeatureFlagManager
{
    return app(FeatureFlagManager::class);
}

it('dispatches FlagToggled with old and new value on toggle', function (): void {
    Event::fake([FlagToggled::class]);

    $dto = manager()->create(['key' => 'k', 'scope_id' => 'team-1', 'value' => false]);
    manager()->toggleValue($dto->id);

    Event::assertDispatched(FlagToggled::class, function (FlagToggled $e): bool {
        return $e->key === 'k'
            && $e->scopeId === 'team-1'
            && $e->oldValue === false
            && $e->newValue === true;
    });
});

it('does not dispatch FlagEvaluated by default', function (): void {
    Event::fake([FlagEvaluated::class]);

    manager()->create(['key' => 'k', 'scope_id' => null, 'value' => true]);
    manager()->isEnabled('k');

    Event::assertNotDispatched(FlagEvaluated::class);
});

it('dispatches FlagEvaluated when evaluation events are enabled', function (): void {
    config()->set('feature-flags.events.evaluation', true);
    Event::fake([FlagEvaluated::class]);

    manager()->create(['key' => 'k', 'scope_id' => null, 'value' => true]);
    manager()->isEnabled('k');

    Event::assertDispatched(FlagEvaluated::class, function (FlagEvaluated $e): bool {
        return $e->key === 'k' && $e->scopeId === null && $e->result === true;
    });
});

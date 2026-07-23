<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Contracts\AuditActorResolver;
use Chemaclass\FeatureFlags\Events\FlagToggled;
use Chemaclass\FeatureFlags\Listeners\RecordFlagChange;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Models\FeatureFlagAudit;
use Illuminate\Support\Facades\Event;

it('does not record anything when auditing is disabled', function (): void {
    config()->set('feature-flags.audit.enabled', false);
    $manager = app(FeatureFlagManager::class);
    $dto = $manager->create(['key' => 'k', 'scope_id' => null, 'value' => false]);

    $manager->toggleValue($dto->id);

    expect(FeatureFlagAudit::query()->count())->toBe(0);
});

it('records a toggle with old/new value and actor when enabled', function (): void {
    // The listener resolves live, so registering it directly is enough.
    Event::listen(FlagToggled::class, RecordFlagChange::class);

    $manager = app(FeatureFlagManager::class);
    $dto = $manager->create(['key' => 'k', 'scope_id' => 'team-1', 'value' => false]);

    $manager->toggleValue($dto->id);

    $audit = FeatureFlagAudit::query()->first();
    expect($audit)->not->toBeNull()
        ->and($audit->key)->toBe('k')
        ->and($audit->scope_id)->toBe('team-1')
        ->and($audit->action)->toBe('toggled')
        ->and($audit->old_value)->toBeFalse()
        ->and($audit->new_value)->toBeTrue();
});

it('resolves the actor via a configured resolver', function (): void {
    config()->set('feature-flags.audit.actor', TestActorResolver::class);
    Event::listen(FlagToggled::class, RecordFlagChange::class);

    $manager = app(FeatureFlagManager::class);
    $dto = $manager->create(['key' => 'k', 'scope_id' => null, 'value' => false]);

    $manager->toggleValue($dto->id);

    expect(FeatureFlagAudit::query()->first()->actor)->toBe('actor-42');
});

class TestActorResolver implements AuditActorResolver
{
    public function resolve(): ?string
    {
        return 'actor-42';
    }
}

<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\DTO\FeatureTransfer;
use Chemaclass\FeatureFlags\Models\FeatureFlag;
use Chemaclass\FeatureFlags\Repository\EloquentFeatureFlagRepository;
use Illuminate\Support\Carbon;

// Targets the SQL/repository layer directly (scope precedence, time windows).
// The caching decorator is covered separately in CacheTest.
beforeEach(function (): void {
    $this->repo = app(EloquentFeatureFlagRepository::class);
});

it('isEnabled returns false when no row exists', function (): void {
    expect($this->repo->isEnabled('missing'))->toBeFalse();
});

it('scope row beats global row for same key', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null,     'value' => false]);
    $this->repo->create(['key' => 'k', 'scope_id' => 'group',  'value' => true]);

    expect($this->repo->isEnabled('k', 'group'))->toBeTrue()
        ->and($this->repo->isEnabled('k'))->toBeFalse();
});

it('falls back to global when scope row missing', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null, 'value' => true]);

    expect($this->repo->isEnabled('k', 'unrelated-scope'))->toBeTrue();
});

it('respects time window: before enabled_from', function (): void {
    Carbon::setTestNow('2026-01-01 00:00');

    $this->repo->create([
        'key' => 'window',
        'scope_id' => null,
        'value' => true,
        'enabled_from' => '2026-06-01',
    ]);

    expect($this->repo->isEnabled('window'))->toBeFalse();

    Carbon::setTestNow('2026-07-01 00:00');
    expect($this->repo->isEnabled('window'))->toBeTrue();

    Carbon::setTestNow();
});

it('respects time window: after enabled_until', function (): void {
    Carbon::setTestNow('2026-12-15 00:00');

    $this->repo->create([
        'key' => 'window',
        'scope_id' => null,
        'value' => true,
        'enabled_until' => '2026-12-01',
    ]);

    expect($this->repo->isEnabled('window'))->toBeFalse();

    Carbon::setTestNow('2026-11-30 00:00');
    expect($this->repo->isEnabled('window'))->toBeTrue();

    Carbon::setTestNow();
});

it('listForScope merges global + scope overrides (scope wins)', function (): void {
    $this->repo->create(['key' => 'a', 'scope_id' => null, 'value' => true]);
    $this->repo->create(['key' => 'b', 'scope_id' => null, 'value' => false]);
    $this->repo->create(['key' => 'b', 'scope_id' => 'g',  'value' => true]);
    $this->repo->create(['key' => 'c', 'scope_id' => 'g',  'value' => true]);

    $result = $this->repo->listForScope('g');

    expect($result)->toHaveKey('a', true)
        ->toHaveKey('b', true)
        ->toHaveKey('c', true);
});

it('listForScope returns globals only when scope id is null', function (): void {
    $this->repo->create(['key' => 'a', 'scope_id' => null, 'value' => true]);
    $this->repo->create(['key' => 'b', 'scope_id' => 'g',  'value' => true]);

    $result = $this->repo->listForScope(null);

    expect($result)->toHaveKey('a', true)
        ->not->toHaveKey('b');
});

it('findById returns a FeatureTransfer or null', function (): void {
    $dto = $this->repo->create(['key' => 'x', 'scope_id' => null, 'value' => true]);

    $found = $this->repo->findById($dto->id);

    expect($found)->toBeInstanceOf(FeatureTransfer::class)
        ->and($found->key)->toBe('x')
        ->and($this->repo->findById('does-not-exist'))->toBeNull();
});

it('findByKeyAndScope matches the (key, scope_id) pair exactly', function (): void {
    $this->repo->create(['key' => 'x', 'scope_id' => null,    'value' => true]);
    $this->repo->create(['key' => 'x', 'scope_id' => 'group', 'value' => false]);

    expect($this->repo->findByKeyAndScope('x', null)->value)->toBeTrue()
        ->and($this->repo->findByKeyAndScope('x', 'group')->value)->toBeFalse()
        ->and($this->repo->findByKeyAndScope('x', 'unknown'))->toBeNull();
});

it('updateOrCreate inserts then updates same row', function (): void {
    $a = $this->repo->updateOrCreate(
        ['key' => 'y', 'scope_id' => null],
        ['value' => false, 'hint' => 'first'],
    );

    $b = $this->repo->updateOrCreate(
        ['key' => 'y', 'scope_id' => null],
        ['value' => true, 'hint' => 'second'],
    );

    expect($a->id)->toBe($b->id)
        ->and(FeatureFlag::query()->where('key', 'y')->count())->toBe(1)
        ->and($b->value)->toBeTrue()
        ->and($b->hint)->toBe('second');
});

it('toggleValue flips the row and returns the new value', function (): void {
    $dto = $this->repo->create(['key' => 'z', 'scope_id' => null, 'value' => false]);

    expect($this->repo->toggleValue($dto->id))->toBeTrue()
        ->and($this->repo->toggleValue($dto->id))->toBeFalse();
});

it('toggleDevByKey flips is_dev for every row with that key', function (): void {
    $this->repo->create(['key' => 'k', 'scope_id' => null,    'value' => true, 'is_dev' => false]);
    $this->repo->create(['key' => 'k', 'scope_id' => 'group', 'value' => true, 'is_dev' => false]);

    expect($this->repo->toggleDevByKey('k'))->toBeTrue()
        ->and(FeatureFlag::query()->where('key', 'k')->where('is_dev', true)->count())->toBe(2)
        ->and($this->repo->toggleDevByKey('k'))->toBeFalse()
        ->and(FeatureFlag::query()->where('key', 'k')->where('is_dev', false)->count())->toBe(2);
});

it('toggleDevByKey returns false when key has no rows', function (): void {
    expect($this->repo->toggleDevByKey('nothing-here'))->toBeFalse();
});

it('update modifies the given fields and returns the DTO', function (): void {
    $dto = $this->repo->create(['key' => 'u', 'scope_id' => null, 'value' => false, 'hint' => 'before']);

    $updated = $this->repo->update($dto->id, ['hint' => 'after', 'value' => true, 'is_dev' => true]);

    expect($updated)->not->toBeNull()
        ->and($updated->hint)->toBe('after')
        ->and($updated->value)->toBeTrue()
        ->and($updated->isDev)->toBeTrue();
});

it('update returns null when the row does not exist', function (): void {
    expect($this->repo->update('01ZZZZZZZZZZZZZZZZZZZZZZZZ', ['hint' => 'x']))->toBeNull();
});

it('delete removes the row and returns true', function (): void {
    $dto = $this->repo->create(['key' => 'd', 'scope_id' => null, 'value' => true]);

    expect($this->repo->delete($dto->id))->toBeTrue()
        ->and($this->repo->findById($dto->id))->toBeNull();
});

<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\DTO\FeatureTransfer;
use Chemaclass\FeatureFlags\Models\FeatureFlag;

it('builds from a FeatureFlag model', function (): void {
    $model = new FeatureFlag([
        'key'           => 'demo',
        'scope_id'      => 'group-X',
        'value'         => true,
        'hint'          => 'My hint',
        'is_dev'        => true,
        'enabled_from'  => '2026-01-01 00:00:00',
        'enabled_until' => '2026-12-31 23:59:59',
    ]);
    $model->id = '01HXXX';

    $dto = FeatureTransfer::fromModel($model);

    expect($dto)->toBeInstanceOf(FeatureTransfer::class)
        ->and($dto->id)->toBe('01HXXX')
        ->and($dto->key)->toBe('demo')
        ->and($dto->scopeId)->toBe('group-X')
        ->and($dto->value)->toBeTrue()
        ->and($dto->hint)->toBe('My hint')
        ->and($dto->isDev)->toBeTrue()
        ->and($dto->enabledFrom)->not->toBeNull()
        ->and($dto->enabledUntil)->not->toBeNull();
});

it('is read-only', function (): void {
    $reflection = new ReflectionClass(FeatureTransfer::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

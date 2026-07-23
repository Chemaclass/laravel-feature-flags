<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Facades;

use Chemaclass\FeatureFlags\Contracts\FeatureKey;
use Chemaclass\FeatureFlags\DTO\FeatureTransfer;
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isEnabled(FeatureKey|string $flag, ?string $scopeId = null)
 * @method static array<string, bool> allEnabled(array<int, FeatureKey|string> $flags, ?string $scopeId = null)
 * @method static array<string, bool> all(?string $scopeId)
 * @method static ?FeatureTransfer findById(string $id)
 * @method static ?FeatureTransfer findByKeyAndScope(string $key, ?string $scopeId)
 * @method static FeatureTransfer create(array<string, mixed> $data)
 * @method static FeatureTransfer updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values)
 * @method static ?FeatureTransfer update(string $id, array<string, mixed> $values)
 * @method static bool delete(string $id)
 * @method static bool toggleValue(string $id)
 * @method static bool toggleDevByKey(string $key)
 *
 * @see FeatureFlagManager
 */
final class FeatureFlag extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FeatureFlagManager::class;
    }
}

<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Pennant;

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\Contracts\FeatureScopeable;

/**
 * Bridges Laravel Pennant onto this package: Feature::active('x') resolves
 * through FeatureFlagManager, so teams already on Pennant can adopt the package
 * as their persistence + admin layer without rewriting call sites.
 *
 * Reads are the primary path. Pennant's rich scope objects are mapped to this
 * package's string scope ids.
 */
final class FeatureFlagsPennantDriver implements Driver
{
    private const DRIVER = 'feature-flags';

    /** @var array<string, callable(mixed):mixed> */
    private array $resolvers = [];

    public function __construct(
        private readonly FeatureFlagManager $manager,
    ) {}

    public function define(string $feature, callable $resolver): void
    {
        $this->resolvers[$feature] = $resolver;
    }

    public function defined(): array
    {
        return array_keys($this->resolvers);
    }

    public function getAll(array $features): array
    {
        $result = [];
        foreach ($features as $feature => $scopes) {
            $result[$feature] = array_map(
                fn (mixed $scope): bool => $this->get($feature, $scope),
                $scopes,
            );
        }

        return $result;
    }

    public function get(string $feature, mixed $scope): bool
    {
        return $this->manager->isEnabled($feature, $this->scopeToId($scope));
    }

    public function set(string $feature, mixed $scope, mixed $value): void
    {
        $this->manager->updateOrCreate(
            ['key' => $feature, 'scope_id' => $this->scopeToId($scope)],
            ['value' => (bool) $value],
        );
    }

    public function setForAllScopes(string $feature, mixed $value): void
    {
        // This package keys overrides per scope; the closest equivalent is the
        // global (null-scope) row, which every scope falls back to.
        $this->manager->updateOrCreate(
            ['key' => $feature, 'scope_id' => null],
            ['value' => (bool) $value],
        );
    }

    public function delete(string $feature, mixed $scope): void
    {
        $flag = $this->manager->findByKeyAndScope($feature, $this->scopeToId($scope));
        if ($flag !== null) {
            $this->manager->delete($flag->id);
        }
    }

    /**
     * @param  array<int, string>|null  $features
     */
    public function purge(?array $features): void
    {
        if ($features === null) {
            return;
        }

        foreach ($features as $feature) {
            $flag = $this->manager->findByKeyAndScope($feature, null);
            if ($flag !== null) {
                $this->manager->delete($flag->id);
            }
        }
    }

    private function scopeToId(mixed $scope): ?string
    {
        if ($scope === null) {
            return null;
        }
        if (is_string($scope)) {
            return $scope;
        }
        if ($scope instanceof FeatureScopeable) {
            $id = $scope->toFeatureIdentifier(self::DRIVER);

            return is_scalar($id) ? (string) $id : null;
        }
        if ($scope instanceof Model) {
            return (string) $scope->getKey();
        }

        return is_scalar($scope) ? (string) $scope : null;
    }
}

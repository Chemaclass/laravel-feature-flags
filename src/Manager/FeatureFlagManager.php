<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Manager;

use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\Contracts\FeatureKey;
use Chemaclass\FeatureFlags\DTO\FeatureTransfer;

final readonly class FeatureFlagManager
{
    public function __construct(
        private FeatureFlagRepository $repository,
    ) {}

    public function isEnabled(FeatureKey|string $flag, ?string $scopeId = null): bool
    {
        $key = $flag instanceof FeatureKey ? $flag->key() : $flag;

        return $this->repository->isEnabled($key, $scopeId);
    }

    /**
     * Evaluate many flags at once for the same scope (single query).
     *
     * @param  list<FeatureKey|string>  $flags
     * @return array<string, bool>
     */
    public function allEnabled(array $flags, ?string $scopeId = null): array
    {
        $keys = array_map(
            static fn (FeatureKey|string $flag): string => $flag instanceof FeatureKey ? $flag->key() : $flag,
            $flags,
        );

        return $this->repository->allEnabled($keys, $scopeId);
    }

    /**
     * @return array<string, bool>
     */
    public function all(?string $scopeId): array
    {
        return $this->repository->listForScope($scopeId);
    }

    public function findById(string $id): ?FeatureTransfer
    {
        return $this->repository->findById($id);
    }

    public function findByKeyAndScope(string $key, ?string $scopeId): ?FeatureTransfer
    {
        return $this->repository->findByKeyAndScope($key, $scopeId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FeatureTransfer
    {
        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function updateOrCreate(array $attributes, array $values): FeatureTransfer
    {
        return $this->repository->updateOrCreate($attributes, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(string $id, array $values): ?FeatureTransfer
    {
        return $this->repository->update($id, $values);
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function toggleValue(string $id): bool
    {
        return $this->repository->toggleValue($id);
    }

    public function toggleDevByKey(string $key): bool
    {
        return $this->repository->toggleDevByKey($key);
    }
}

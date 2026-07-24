<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Manager;

use Chemaclass\FeatureFlags\Analytics\ExposureRecorder;
use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\Contracts\FeatureKey;
use Chemaclass\FeatureFlags\DTO\ExposureStats;
use Chemaclass\FeatureFlags\DTO\FeatureTransfer;
use Chemaclass\FeatureFlags\DTO\VariantResult;

final readonly class FeatureFlagManager
{
    public function __construct(
        private FeatureFlagRepository $repository,
        private ExposureRecorder $exposures = new ExposureRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $context  Attributes for targeting rules.
     */
    public function isEnabled(FeatureKey|string $flag, ?string $scopeId = null, array $context = []): bool
    {
        $key = $flag instanceof FeatureKey ? $flag->key() : $flag;

        $result = $this->repository->isEnabled($key, $scopeId, $context);
        $this->exposures->recordEvaluation($key, $result);

        return $result;
    }

    /**
     * Evaluate many flags at once for the same scope (single query).
     *
     * @param  list<FeatureKey|string>  $flags
     * @param  array<string, mixed>  $context
     * @return array<string, bool>
     */
    public function allEnabled(array $flags, ?string $scopeId = null, array $context = []): array
    {
        $keys = array_map(
            static fn (FeatureKey|string $flag): string => $flag instanceof FeatureKey ? $flag->key() : $flag,
            $flags,
        );

        $results = $this->repository->allEnabled($keys, $scopeId, $context);
        foreach ($results as $key => $result) {
            $this->exposures->recordEvaluation($key, $result);
        }

        return $results;
    }

    /**
     * Select the active variant for a flag, or null when it has none or is
     * disabled for the scope.
     *
     * @param  array<string, mixed>  $context
     */
    public function variant(FeatureKey|string $flag, ?string $scopeId = null, array $context = []): ?VariantResult
    {
        $key = $flag instanceof FeatureKey ? $flag->key() : $flag;

        $variant = $this->repository->variant($key, $scopeId, $context);
        if ($variant !== null) {
            $this->exposures->recordVariant($key, $variant->name);
        }

        return $variant;
    }

    /**
     * @return array<string, bool>
     */
    public function all(?string $scopeId): array
    {
        return $this->repository->listForScope($scopeId);
    }

    /**
     * Aggregate exposure counts per flag (requires analytics enabled).
     *
     * @return list<ExposureStats>
     */
    public function exposureStats(): array
    {
        return $this->exposures->stats();
    }

    /**
     * Keys unchanged for $days and constant across all rows (cleanup candidates).
     *
     * @return list<array{key: string, value: bool, days: int}>
     */
    public function staleFlags(int $days): array
    {
        return $this->repository->staleKeys($days);
    }

    /**
     * @return list<string>
     */
    public function distinctKeys(): array
    {
        return $this->repository->distinctKeys();
    }

    /**
     * @return list<FeatureTransfer>
     */
    public function allFlags(): array
    {
        return $this->repository->allFlags();
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

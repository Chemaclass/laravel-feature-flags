<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Contracts;

use Chemaclass\FeatureFlags\DTO\FeatureTransfer;

interface FeatureFlagRepository
{
    public function isEnabled(string $key, ?string $scopeId = null): bool;

    /**
     * @return array<string, bool>
     */
    public function listForScope(?string $scopeId): array;

    public function findById(string $id): ?FeatureTransfer;

    public function findByKeyAndScope(string $key, ?string $scopeId): ?FeatureTransfer;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FeatureTransfer;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function updateOrCreate(array $attributes, array $values): FeatureTransfer;

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(string $id, array $values): ?FeatureTransfer;

    public function delete(string $id): bool;

    public function toggleValue(string $id): bool;

    public function toggleDevByKey(string $key): bool;
}

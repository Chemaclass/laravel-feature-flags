<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Contracts;

use Chemaclass\FeatureFlags\DTO\FeatureTransfer;
use Chemaclass\FeatureFlags\DTO\VariantResult;

interface FeatureFlagRepository
{
    /**
     * @param  array<string, mixed>  $context  Attributes for targeting rules.
     */
    public function isEnabled(string $key, ?string $scopeId = null, array $context = []): bool;

    /**
     * Evaluate many keys at once for the same scope.
     *
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $context
     * @return array<string, bool>
     */
    public function allEnabled(array $keys, ?string $scopeId = null, array $context = []): array;

    /**
     * Select the active variant for a flag, or null when it has no variants or
     * is disabled for the scope.
     *
     * @param  array<string, mixed>  $context
     */
    public function variant(string $key, ?string $scopeId = null, array $context = []): ?VariantResult;

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

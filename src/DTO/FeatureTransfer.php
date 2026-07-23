<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\DTO;

use Chemaclass\FeatureFlags\Models\FeatureFlag;
use DateTimeInterface;

final readonly class FeatureTransfer
{
    /**
     * @param  array<int, array<string, mixed>>|null  $rules
     */
    public function __construct(
        public string $id,
        public string $key,
        public bool $value,
        public ?string $scopeId,
        public ?string $environment,
        public ?string $hint,
        public bool $isDev,
        public ?DateTimeInterface $enabledFrom,
        public ?DateTimeInterface $enabledUntil,
        public ?int $rolloutPercentage = null,
        public ?array $rules = null,
    ) {}

    public static function fromModel(FeatureFlag $model): self
    {
        return new self(
            id: (string) $model->getKey(),
            key: $model->key,
            value: (bool) $model->value,
            scopeId: $model->scope_id,
            environment: $model->environment,
            hint: $model->hint,
            isDev: (bool) $model->is_dev,
            enabledFrom: $model->enabled_from,
            enabledUntil: $model->enabled_until,
            rolloutPercentage: $model->rollout_percentage,
            rules: $model->rules,
        );
    }
}

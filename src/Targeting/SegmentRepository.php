<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Targeting;

use Chemaclass\FeatureFlags\Models\FeatureFlagSegment;

/**
 * Loads and stores reusable targeting segments. `all()` is memoized per request
 * so evaluating rules that reference segments costs at most one query.
 */
final class SegmentRepository
{
    /** @var array<string, list<array<string, mixed>>>|null */
    private ?array $cache = null;

    /**
     * @return array<string, list<array<string, mixed>>> name => conditions
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $map = [];
        foreach (FeatureFlagSegment::query()->get(['name', 'conditions']) as $segment) {
            /** @var list<array<string, mixed>> $conditions */
            $conditions = $segment->conditions;
            $map[$segment->name] = $conditions;
        }

        return $this->cache = $map;
    }

    /**
     * @param  list<array<string, mixed>>  $conditions
     */
    public function define(string $name, array $conditions, ?string $description = null): void
    {
        FeatureFlagSegment::query()->updateOrCreate(
            ['name' => $name],
            ['conditions' => $conditions, 'description' => $description],
        );

        $this->cache = null;
    }

    public function delete(string $name): bool
    {
        $deleted = (bool) FeatureFlagSegment::query()->where('name', $name)->delete();
        $this->cache = null;

        return $deleted;
    }
}

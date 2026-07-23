<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Repository;

use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\DTO\FeatureTransfer;
use Chemaclass\FeatureFlags\Events\FlagEvaluated;
use Chemaclass\FeatureFlags\Events\FlagToggled;
use Chemaclass\FeatureFlags\Models\FeatureFlag;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;

final class EloquentFeatureFlagRepository implements FeatureFlagRepository
{
    /** @var class-string<FeatureFlag> */
    private string $modelClass;

    public function __construct(
        private readonly Dispatcher $events,
    ) {
        /** @var class-string<FeatureFlag> $cls */
        $cls = config('feature-flags.model', FeatureFlag::class);
        $this->modelClass = $cls;
    }

    public function isEnabled(string $key, ?string $scopeId = null): bool
    {
        $row = $this->query()
            ->where('key', $key)
            ->when(
                $scopeId,
                fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                    ->where('scope_id', $scopeId)
                    ->orWhereNull('scope_id')),
                fn (Builder $q) => $q->whereNull('scope_id'),
            )
            ->tap(fn (Builder $q) => $this->timeWindowScope($q))
            ->orderByRaw('scope_id IS NULL ASC')
            ->first();

        $result = $row !== null && $row->value
            ? $this->passesRollout($key, $scopeId, $row->rollout_percentage)
            : false;

        if (config('feature-flags.events.evaluation', false)) {
            $this->events->dispatch(new FlagEvaluated($key, $scopeId, $result));
        }

        return $result;
    }

    /**
     * Deterministic percentage rollout: the same key+scope always lands in the
     * same bucket, so a flag at X% is enabled for a stable ~X% of scopes. Null
     * percentage means no gate (pure boolean). For a null scope the bucket is
     * derived from the key alone, so global rollout is effectively all-or-nothing
     * at the threshold — percentage rollout is meant to be paired with a scope.
     */
    private function passesRollout(string $key, ?string $scopeId, ?int $percentage): bool
    {
        if ($percentage === null) {
            return true;
        }
        if ($percentage <= 0) {
            return false;
        }
        if ($percentage >= 100) {
            return true;
        }

        $bucket = crc32($key.':'.($scopeId ?? '')) % 100;

        return $bucket < $percentage;
    }

    public function allEnabled(array $keys, ?string $scopeId = null): array
    {
        /** @var array<string, bool> $result */
        $result = array_fill_keys($keys, false);
        if ($keys === []) {
            return $result;
        }

        $rows = $this->query()
            ->whereIn('key', $keys)
            ->when(
                $scopeId,
                fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                    ->where('scope_id', $scopeId)
                    ->orWhereNull('scope_id')),
                fn (Builder $q) => $q->whereNull('scope_id'),
            )
            ->tap(fn (Builder $q) => $this->timeWindowScope($q))
            ->orderByRaw('scope_id IS NULL ASC')
            ->get(['key', 'value', 'scope_id']);

        // Ordered scope-first: the first row seen per key wins (scope beats global).
        $seen = [];
        foreach ($rows as $row) {
            if (isset($seen[$row->key])) {
                continue;
            }
            $seen[$row->key] = true;
            $result[$row->key] = (bool) $row->value;
        }

        return $result;
    }

    public function listForScope(?string $scopeId): array
    {
        return $this->query()
            ->where(function (Builder $q) use ($scopeId): void {
                $q->whereNull('scope_id');
                if ($scopeId !== null) {
                    $q->orWhere('scope_id', $scopeId);
                }
            })
            ->tap(fn (Builder $q) => $this->timeWindowScope($q))
            ->orderByRaw('scope_id IS NULL DESC')
            ->pluck('value', 'key')
            ->toArray();
    }

    public function findById(string $id): ?FeatureTransfer
    {
        $m = $this->modelClass::find($id);

        return $m ? FeatureTransfer::fromModel($m) : null;
    }

    public function findByKeyAndScope(string $key, ?string $scopeId): ?FeatureTransfer
    {
        $m = $this->query()
            ->where('key', $key)
            ->where('scope_id', $scopeId)
            ->first();

        return $m ? FeatureTransfer::fromModel($m) : null;
    }

    public function create(array $data): FeatureTransfer
    {
        $m = $this->modelClass::create($data);

        return FeatureTransfer::fromModel($m);
    }

    public function updateOrCreate(array $attributes, array $values): FeatureTransfer
    {
        $m = $this->modelClass::updateOrCreate($attributes, $values);

        return FeatureTransfer::fromModel($m);
    }

    public function update(string $id, array $values): ?FeatureTransfer
    {
        $m = $this->modelClass::find($id);
        if ($m === null) {
            return null;
        }

        $m->fill($values);
        $m->save();

        return FeatureTransfer::fromModel($m);
    }

    public function delete(string $id): bool
    {
        return (bool) $this->modelClass::query()->whereKey($id)->delete();
    }

    public function toggleValue(string $id): bool
    {
        /** @var FeatureFlag $m */
        $m = $this->modelClass::findOrFail($id);
        $oldValue = (bool) $m->value;
        $m->value = ! $oldValue;
        $m->save();

        $this->events->dispatch(new FlagToggled($m->key, $m->scope_id, $oldValue, $m->value));

        return $m->value;
    }

    public function toggleDevByKey(string $key): bool
    {
        $rows = $this->query()->where('key', $key)->get();
        if ($rows->isEmpty()) {
            return false;
        }
        $newValue = ! $rows->contains('is_dev', true);
        $this->query()->where('key', $key)->update(['is_dev' => $newValue]);

        return $newValue;
    }

    /**
     * @return Builder<FeatureFlag>
     */
    private function query(): Builder
    {
        return $this->modelClass::query();
    }

    /**
     * @param  Builder<FeatureFlag>  $q
     */
    private function timeWindowScope(Builder $q): void
    {
        $q->where(fn (Builder $w) => $w
            ->whereNull('enabled_from')
            ->orWhere('enabled_from', '<=', now()))
            ->where(fn (Builder $w) => $w
                ->whereNull('enabled_until')
                ->orWhere('enabled_until', '>=', now()));
    }
}

<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Repository;

use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\DTO\FeatureTransfer;
use Chemaclass\FeatureFlags\Models\FeatureFlag;
use Illuminate\Database\Eloquent\Builder;

final class EloquentFeatureFlagRepository implements FeatureFlagRepository
{
    /** @var class-string<FeatureFlag> */
    private string $modelClass;

    public function __construct()
    {
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

        return (bool) ($row->value ?? false);
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
        $m->value = ! $m->value;
        $m->save();

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

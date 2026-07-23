<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Repository;

use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\DTO\FeatureTransfer;
use Chemaclass\FeatureFlags\DTO\VariantResult;
use Chemaclass\FeatureFlags\Events\FlagEvaluated;
use Chemaclass\FeatureFlags\Events\FlagToggled;
use Chemaclass\FeatureFlags\Models\FeatureFlag;
use Chemaclass\FeatureFlags\Targeting\RuleEvaluator;
use Chemaclass\FeatureFlags\Variants\VariantSelector;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;

final class EloquentFeatureFlagRepository implements FeatureFlagRepository
{
    /** @var class-string<FeatureFlag> */
    private string $modelClass;

    public function __construct(
        private readonly Dispatcher $events,
        private readonly RuleEvaluator $rules = new RuleEvaluator,
        private readonly VariantSelector $variants = new VariantSelector,
    ) {
        /** @var class-string<FeatureFlag> $cls */
        $cls = config('feature-flags.model', FeatureFlag::class);
        $this->modelClass = $cls;
    }

    public function isEnabled(string $key, ?string $scopeId = null, array $context = []): bool
    {
        $result = $this->evaluate($key, $scopeId, $context, []);

        if (config('feature-flags.events.evaluation', false)) {
            $this->events->dispatch(new FlagEvaluated($key, $scopeId, $result));
        }

        return $result;
    }

    /**
     * Evaluate a flag, honouring the kill switch, targeting rules, rollout and
     * prerequisites. `$chain` tracks the prerequisite path to break cycles.
     *
     * @param  array<string, mixed>  $context
     * @param  list<string>  $chain
     */
    private function evaluate(string $key, ?string $scopeId, array $context, array $chain): bool
    {
        if ($this->isKilled($key) || in_array($key, $chain, true)) {
            return false;
        }

        $row = $this->winningRow($key, $scopeId);

        if (! $this->resolveRow($row, $key, $scopeId, $context)) {
            return false;
        }

        $prerequisites = $row->prerequisites ?? [];
        foreach ($prerequisites as $prerequisite) {
            if (! $this->evaluate($prerequisite, $scopeId, $context, [...$chain, $key])) {
                return false;
            }
        }

        return true;
    }

    private function isKilled(string $key): bool
    {
        /** @var list<string> $killed */
        $killed = config('feature-flags.kill_switch', []);

        return in_array($key, $killed, true);
    }

    private function winningRow(string $key, ?string $scopeId): ?FeatureFlag
    {
        return $this->query()
            ->where('key', $key)
            ->when(
                $scopeId,
                fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                    ->where('scope_id', $scopeId)
                    ->orWhereNull('scope_id')),
                fn (Builder $q) => $q->whereNull('scope_id'),
            )
            ->tap(fn (Builder $q) => $this->environmentScope($q))
            ->tap(fn (Builder $q) => $this->timeWindowScope($q))
            ->orderByRaw('scope_id IS NULL ASC')
            ->orderByRaw('environment IS NULL ASC')
            ->first();
    }

    /**
     * Resolve a winning row to a boolean: targeting rules first (a matching rule
     * overrides), then the boolean value gated by percentage rollout.
     *
     * @param  array<string, mixed>  $context
     */
    private function resolveRow(?FeatureFlag $row, string $key, ?string $scopeId, array $context): bool
    {
        if ($row === null) {
            return false;
        }

        $rules = $row->rules;
        if (is_array($rules) && $rules !== []) {
            $ruled = $this->rules->matches($rules, $context);
            if ($ruled !== null) {
                return $ruled;
            }
        }

        return $row->value && $this->passesRollout($key, $scopeId, $row->rollout_percentage);
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

    public function allEnabled(array $keys, ?string $scopeId = null, array $context = []): array
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
            ->tap(fn (Builder $q) => $this->environmentScope($q))
            ->tap(fn (Builder $q) => $this->timeWindowScope($q))
            ->orderByRaw('scope_id IS NULL ASC')
            ->orderByRaw('environment IS NULL ASC')
            ->get();

        // Ordered scope-first: the first row seen per key wins (scope beats global).
        $seen = [];
        foreach ($rows as $row) {
            if (isset($seen[$row->key])) {
                continue;
            }
            $seen[$row->key] = true;

            if ($this->isKilled($row->key) || ! $this->resolveRow($row, $row->key, $scopeId, $context)) {
                $result[$row->key] = false;

                continue;
            }

            $result[$row->key] = true;
            foreach ($row->prerequisites ?? [] as $prerequisite) {
                if (! $this->evaluate($prerequisite, $scopeId, $context, [$row->key])) {
                    $result[$row->key] = false;
                    break;
                }
            }
        }

        return $result;
    }

    public function variant(string $key, ?string $scopeId = null, array $context = []): ?VariantResult
    {
        if (! $this->isEnabled($key, $scopeId, $context)) {
            return null;
        }

        $row = $this->winningRow($key, $scopeId);
        $variants = $row?->variants;
        if ($row === null || ! is_array($variants) || $variants === []) {
            return null;
        }

        $name = $this->variants->select($variants, $key, $scopeId);
        if ($name === null) {
            return null;
        }

        $payloads = $row->variant_payloads ?? [];

        return new VariantResult($name, $payloads[$name] ?? null);
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
            ->tap(fn (Builder $q) => $this->environmentScope($q))
            ->tap(fn (Builder $q) => $this->timeWindowScope($q))
            // Least specific first so pluck's later rows (more specific) win:
            // scope dominates environment.
            ->orderByRaw('scope_id IS NULL DESC')
            ->orderByRaw('environment IS NULL DESC')
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
     * Limit to rows for the current environment or the env-agnostic (null) rows.
     *
     * @param  Builder<FeatureFlag>  $q
     */
    private function environmentScope(Builder $q): void
    {
        $env = $this->currentEnvironment();

        $q->where(fn (Builder $w) => $w
            ->whereNull('environment')
            ->orWhere('environment', $env));
    }

    private function currentEnvironment(): string
    {
        /** @var string|null $override */
        $override = config('feature-flags.environment.current');

        return $override ?? (string) app()->environment();
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

<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Repository;

use Chemaclass\FeatureFlags\Contracts\FeatureFlagRepository;
use Chemaclass\FeatureFlags\DTO\FeatureTransfer;
use Chemaclass\FeatureFlags\DTO\VariantResult;
use Chemaclass\FeatureFlags\Events\FlagsChanged;
use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Wraps a repository with per-request memoization and an optional persistent
 * cache store. Writes flush both layers so evaluation always reflects the
 * latest state. Behaviour is identical to the inner repository when no store
 * is configured (memoization only, no cross-request cache).
 */
final class CachingFeatureFlagRepository implements FeatureFlagRepository
{
    /** @var array<string, bool> */
    private array $enabledMemo = [];

    /** @var array<string, array<string, bool>> */
    private array $listMemo = [];

    private ?string $store;

    private int $ttl;

    private string $prefix;

    /**
     * @param  array{store?: ?string, ttl?: int, prefix?: string}  $config
     */
    public function __construct(
        private readonly FeatureFlagRepository $inner,
        private readonly CacheFactory $cache,
        array $config = [],
    ) {
        $this->store = $config['store'] ?? null;
        $this->ttl = $config['ttl'] ?? 60;
        $this->prefix = $config['prefix'] ?? 'feature-flags';
    }

    public function isEnabled(string $key, ?string $scopeId = null, array $context = []): bool
    {
        $memoKey = $this->memoKey($key, $scopeId, $context);
        if (array_key_exists($memoKey, $this->enabledMemo)) {
            return $this->enabledMemo[$memoKey];
        }

        $value = $this->remember(
            'enabled:'.$memoKey,
            fn (): bool => $this->inner->isEnabled($key, $scopeId, $context),
        );

        return $this->enabledMemo[$memoKey] = $value;
    }

    public function allEnabled(array $keys, ?string $scopeId = null, array $context = []): array
    {
        $result = [];
        $missing = [];
        foreach ($keys as $key) {
            $memoKey = $this->memoKey($key, $scopeId, $context);
            if (array_key_exists($memoKey, $this->enabledMemo)) {
                $result[$key] = $this->enabledMemo[$memoKey];
            } else {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            foreach ($this->inner->allEnabled($missing, $scopeId, $context) as $key => $value) {
                $this->enabledMemo[$this->memoKey($key, $scopeId, $context)] = $value;
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function variant(string $key, ?string $scopeId = null, array $context = []): ?VariantResult
    {
        // Variant selection is deterministic for a given key+scope; delegate
        // straight to the inner repository (it internally reuses evaluation).
        return $this->inner->variant($key, $scopeId, $context);
    }

    public function listForScope(?string $scopeId): array
    {
        $memoKey = $scopeId ?? '';
        if (array_key_exists($memoKey, $this->listMemo)) {
            return $this->listMemo[$memoKey];
        }

        $value = $this->remember(
            'list:'.$memoKey,
            fn (): array => $this->inner->listForScope($scopeId),
        );

        return $this->listMemo[$memoKey] = $value;
    }

    public function staleKeys(int $days): array
    {
        return $this->inner->staleKeys($days);
    }

    public function distinctKeys(): array
    {
        return $this->inner->distinctKeys();
    }

    public function allFlags(): array
    {
        return $this->inner->allFlags();
    }

    public function findById(string $id): ?FeatureTransfer
    {
        return $this->inner->findById($id);
    }

    public function findByKeyAndScope(string $key, ?string $scopeId): ?FeatureTransfer
    {
        return $this->inner->findByKeyAndScope($key, $scopeId);
    }

    public function create(array $data): FeatureTransfer
    {
        return $this->tapFlush(fn (): FeatureTransfer => $this->inner->create($data));
    }

    public function updateOrCreate(array $attributes, array $values): FeatureTransfer
    {
        return $this->tapFlush(fn (): FeatureTransfer => $this->inner->updateOrCreate($attributes, $values));
    }

    public function update(string $id, array $values): ?FeatureTransfer
    {
        return $this->tapFlush(fn (): ?FeatureTransfer => $this->inner->update($id, $values));
    }

    public function delete(string $id): bool
    {
        return $this->tapFlush(fn (): bool => $this->inner->delete($id));
    }

    public function toggleValue(string $id): bool
    {
        return $this->tapFlush(fn (): bool => $this->inner->toggleValue($id));
    }

    public function toggleDevByKey(string $key): bool
    {
        return $this->tapFlush(fn (): bool => $this->inner->toggleDevByKey($key));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function memoKey(string $key, ?string $scopeId, array $context = []): string
    {
        $suffix = $context === [] ? '' : '|'.md5(serialize($context));

        return $key.'|'.($scopeId ?? '').$suffix;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function tapFlush(Closure $callback): mixed
    {
        $result = $callback();
        $this->flush();

        return $result;
    }

    private function flush(): void
    {
        $this->enabledMemo = [];
        $this->listMemo = [];

        $store = $this->cacheStore();
        if ($store !== null) {
            // Bump the namespace version instead of enumerating keys, so a single
            // write invalidates every cached evaluation on any cache driver.
            $store->forever($this->prefix.':version', $this->version($store) + 1);
        }

        // Tell other nodes to invalidate too (opt-in). event() resolves the live
        // dispatcher so a faked dispatcher in tests still sees it.
        if ((bool) config('feature-flags.realtime.enabled', false)) {
            event(new FlagsChanged);
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function remember(string $key, Closure $callback): mixed
    {
        $store = $this->cacheStore();
        if ($store === null) {
            return $callback();
        }

        return $store->remember(
            $this->prefix.':v'.$this->version($store).':'.$key,
            $this->ttl,
            $callback,
        );
    }

    private function cacheStore(): ?CacheRepository
    {
        return $this->store === null ? null : $this->cache->store($this->store);
    }

    private function version(CacheRepository $store): int
    {
        $version = $store->get($this->prefix.':version');

        return is_numeric($version) ? (int) $version : 0;
    }
}

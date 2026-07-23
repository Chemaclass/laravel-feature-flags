<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Listeners;

use Chemaclass\FeatureFlags\Events\FlagsChanged;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

/**
 * On a broadcast FlagsChanged, bump this node's cache namespace version so the
 * next evaluation re-queries. Idempotent: bumping to a higher version is always
 * safe, so echoed or duplicate broadcasts cannot corrupt state.
 */
final readonly class InvalidateFlagCache
{
    public function __construct(
        private CacheFactory $cache,
    ) {}

    public function handle(FlagsChanged $event): void
    {
        /** @var array{store?: ?string, prefix?: string} $config */
        $config = config('feature-flags.cache', []);
        $store = $config['store'] ?? null;
        if ($store === null) {
            return;
        }

        $prefix = $config['prefix'] ?? 'feature-flags';
        $repository = $this->cache->store($store);
        $versionKey = $prefix.':version';
        $current = $repository->get($versionKey);
        $version = is_numeric($current) ? (int) $current : 0;

        $repository->forever($versionKey, $version + 1);
    }
}

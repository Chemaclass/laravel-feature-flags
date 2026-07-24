# Exposure analytics

See how often flags are evaluated and how variants split — the raw material for experiments.
Off by default; when enabled, each evaluation increments an aggregate counter.

## Enable it

```php
// config/feature-flags.php
'analytics' => [
    'enabled' => true,
    'table' => 'feature_flag_exposures',
],
```

```dotenv
FEATURE_FLAGS_ANALYTICS_ENABLED=true
```

Publish + migrate so the `feature_flag_exposures` table exists (`php artisan migrate` after the
package migrations are loaded).

## What gets recorded

Every call through `FeatureFlagManager` (facade, `@feature`, middleware, the HTTP API, …):

- `isEnabled` / `allEnabled` → increments `(key, enabled)` — separate counters for on and off.
- `variant` → increments `(key, variant)` for the selected variant.

Counts are aggregated (one row per key/variant/result), so storage stays bounded regardless of
traffic.

## Read the stats

```bash
php artisan flag:stats          # table: key, enabled, disabled, total, variants
php artisan flag:stats --json   # machine-readable
```

Or in code:

```php
foreach (app(FeatureFlagManager::class)->exposureStats() as $stat) {
    $stat->key; $stat->enabled; $stat->disabled; $stat->total(); $stat->variants; // ['blue' => 42, ...]
}
```

## Performance

Each recorded exposure is a small DB write. That's fine for most apps, but on very hot paths
consider:

- keeping analytics **off** for the highest-traffic flags and reading them from your own metrics,
- moving the write behind a queue, or
- sampling (record a fraction of exposures).

Analytics is independent of the evaluation cache — exposures count logical evaluations, so a
cache hit still counts.

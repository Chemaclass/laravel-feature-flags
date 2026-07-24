# Usage

## Define flag keys with an enum

The `FeatureKey` contract keeps keys type-safe and centralized.

```php
use Chemaclass\FeatureFlags\Contracts\FeatureKey;

enum AppFeature: string implements FeatureKey
{
    case NewDashboard = 'new-dashboard';
    case BetaBilling  = 'beta-billing';
    case AsyncExports = 'async-exports';

    public function key(): string
    {
        return $this->value;
    }
}
```

Raw strings work everywhere too. The enum is optional sugar.

## Check a flag

Two entry points: the **facade** (terser, recommended) or the **manager** resolved from the container.

```php
use Chemaclass\FeatureFlags\Facades\FeatureFlag;

// Global check
FeatureFlag::isEnabled(AppFeature::NewDashboard);

// Scoped check. $scopeId is any string your app decides on
// (team id, region code, cohort name, customer id, etc.)
FeatureFlag::isEnabled(AppFeature::NewDashboard, $scopeId);

// String key also works
FeatureFlag::isEnabled('new-dashboard', $scopeId);
```

Or via the container:

```php
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;

app(FeatureFlagManager::class)->isEnabled('new-dashboard', $scopeId);
```

The facade resolves to the same singleton, so custom repository bindings still flow through.

### Check many flags at once

`allEnabled()` evaluates several keys for the same scope in a **single query** — use it
instead of a loop of `isEnabled()` calls when you need a batch (dashboards, API payloads):

```php
FeatureFlag::allEnabled([AppFeature::NewDashboard, 'beta-search'], $scopeId);
// => ['new-dashboard' => true, 'beta-search' => false]
```

Missing keys resolve to `false`. Scope precedence matches `isEnabled()`.

### In Blade templates

Use the `@feature` conditional. With no scope it resolves the current scope through the
bound resolver — same behavior as the middleware:

```blade
@feature('new-dashboard')
    <x-new-dashboard />
@else
    <x-legacy-dashboard />
@endfeature

{{-- Inverse --}}
@unlessfeature('beta-search')
    <x-classic-search />
@endfeature

{{-- Explicit scope overrides the resolver --}}
@feature('new-dashboard', $team->id)
    <x-new-dashboard />
@endfeature
```

Close both `@feature` and `@unlessfeature` with `@endfeature`.

### Resolution rules

1. Row exists for `(key, scope_id = $scopeId)` → use its `value`.
2. Otherwise row exists for `(key, scope_id = null)` (global) → use its `value`.
3. Otherwise → `false`.
4. Time window: if `enabled_from` / `enabled_until` is set, the row only counts inside that window.

Scoped row **always wins over global** when both exist.

## List all flags for a scope

```php
$flags = FeatureFlag::all($scopeId);
// ['new-dashboard' => true, 'beta-billing' => false, ...]
```

Returns global flags merged with scope overrides (scope wins).

## Create

```php
FeatureFlag::create([
    'key'      => 'new-dashboard',
    'scope_id' => null,
    'value'    => true,
    'hint'     => 'Q2 redesign rollout',
]);
```

## Update (full upsert by `(key, scope_id)`)

```php
FeatureFlag::updateOrCreate(
    ['key' => 'new-dashboard', 'scope_id' => null],
    [
        'value'         => true,
        'hint'          => 'Q2 redesign rollout',
        'is_dev'        => false,
        'enabled_from'  => now(),
        'enabled_until' => now()->addMonth(),
    ],
);
```

## Update (partial by id)

```php
FeatureFlag::update($flagId, ['hint' => 'Renamed rollout']);
FeatureFlag::update($flagId, ['enabled_until' => now()->addWeek()]);
```

Returns the updated `FeatureTransfer` DTO, or `null` if the id is missing.

## Delete

```php
FeatureFlag::delete($flagId); // true on success, false if id is missing
```

## Toggle

```php
$newValue = FeatureFlag::toggleValue($flagId);          // flip one row's value
$newDev   = FeatureFlag::toggleDevByKey('new-dashboard'); // flip is_dev on every row of that key
```

## Find

```php
$transfer = FeatureFlag::findById($id);                            // ?FeatureTransfer
$transfer = FeatureFlag::findByKeyAndScope('new-dashboard', $scopeId);
```

`FeatureTransfer` is a read-only DTO, safe to return from APIs.

## Time windows

```php
FeatureFlag::updateOrCreate(
    ['key' => 'black-friday-banner', 'scope_id' => null],
    [
        'value'         => true,
        'enabled_from'  => '2026-11-27 00:00:00',
        'enabled_until' => '2026-12-01 23:59:59',
    ],
);
```

Outside the window, `isEnabled()` returns `false` even if `value = true`.

## Per-environment values

A flag row can be scoped to one environment via its `environment` column; `null` applies to
every environment. The current environment defaults to `app()->environment()` (override with
`feature-flags.environment.current`). Precedence, most specific first:

1. `(scope = X, environment = current)`
2. `(scope = X, environment = null)`
3. `(scope = null, environment = current)`
4. `(scope = null, environment = null)`

```php
FeatureFlag::updateOrCreate(
    ['key' => 'new-ui', 'scope_id' => null, 'environment' => 'production'],
    ['value' => false],
);
```

Existing env-null rows are unchanged — scope still dominates environment.

## Targeting rules

Gate a flag on attributes of an evaluation context. Rules are stored per flag; a matching
rule overrides the boolean value, so you can target by plan, country, email, cohort, anything:

```php
FeatureFlag::updateOrCreate(
    ['key' => 'new-billing', 'scope_id' => null],
    [
        'value' => false, // default off
        'rules' => [
            ['when' => [['attr' => 'plan', 'op' => 'eq', 'value' => 'pro']], 'then' => true],
            ['when' => [['attr' => 'country', 'op' => 'in', 'value' => ['DE', 'AT']]], 'then' => false],
        ],
    ],
);

FeatureFlag::isEnabled('new-billing', $userId, ['plan' => 'pro', 'country' => 'US']); // true
```

- Conditions inside a `when` **AND** together; rules **OR** (first match wins).
- Operators: `eq`, `neq`, `in`, `not_in`, `gt`, `gte`, `lt`, `lte`, `contains`, `starts_with`, `ends_with`.
- A missing context attribute makes its condition false — never throws.
- No matching rule → falls back to the boolean `value` + rollout.
- The context is part of the cache key, so different contexts never collide.

### Reusable segments

Define a set of conditions once and reference it from many flags with `['segment' => 'name']`
instead of repeating them. Updating the segment updates every flag that uses it (and busts the
evaluation cache):

```php
FeatureFlag::defineSegment('eu-pro', [
    ['attr' => 'plan', 'op' => 'eq', 'value' => 'pro'],
    ['attr' => 'country', 'op' => 'in', 'value' => ['DE', 'AT']],
]);

FeatureFlag::updateOrCreate(['key' => 'new-billing', 'scope_id' => null], [
    'value' => false,
    'rules' => [['when' => [['segment' => 'eu-pro']], 'then' => true]],
]);
```

A segment reference matches when **all** of its conditions match; mix it with attribute
conditions in the same `when`. An unknown segment never matches.

## Variants (A/B/n + payloads)

A flag can carry weighted variants and per-variant payloads. `variant()` returns the
deterministically selected variant for a scope (same key+scope → same variant), or `null`
when the flag has no variants or is disabled:

```php
FeatureFlag::updateOrCreate(
    ['key' => 'homepage', 'scope_id' => null],
    [
        'value' => true,
        'variants' => [
            ['name' => 'control', 'weight' => 50],
            ['name' => 'blue', 'weight' => 50],
        ],
        'variant_payloads' => ['blue' => ['cta' => 'Start free trial']],
    ],
);

$variant = FeatureFlag::variant('homepage', $userId);
$variant?->name;    // 'control' | 'blue'
$variant?->payload; // ['cta' => ...] for 'blue', null otherwise
```

Weights need not sum to 100 (they're normalized). The selection salt is independent of the
percentage-rollout bucket, so the two decisions don't correlate.

## Percentage rollout

Set `rollout_percentage` (0–100) to enable a flag for a deterministic slice of scopes.
The same key+scope always lands in the same bucket, so a flag at `30` is on for a stable
~30% of scopes and never flips between checks:

```php
FeatureFlag::updateOrCreate(
    ['key' => 'new-checkout', 'scope_id' => null],
    ['value' => true, 'rollout_percentage' => 30],
);

FeatureFlag::isEnabled('new-checkout', $userId); // true for ~30% of $userId values
```

- `null` (default) → no gate, pure boolean.
- `value = false` → off regardless of percentage.
- The bucket is derived from `key + scopeId`, so **pair rollout with a scope**. For a global
  (null scope) flag the bucket depends on the key alone, making it effectively all-or-nothing
  at the threshold.
- A scoped override row wins over the global row and applies **its own** percentage.

## Prerequisites & kill switch

A flag can require other flags to be on. If any prerequisite resolves false, the flag is off —
even if its own value is true. Cycles resolve to false (no infinite loop):

```php
FeatureFlag::updateOrCreate(
    ['key' => 'checkout-v2', 'scope_id' => null],
    ['value' => true, 'prerequisites' => ['payments-live', 'new-cart']],
);
```

A global **kill switch** forces any listed key off before any query — a master off-switch for
incidents, driven from the environment:

```php
// config/feature-flags.php
'kill_switch' => array_filter(explode(',', (string) env('FEATURE_FLAGS_KILL_SWITCH', ''))),
```

```dotenv
FEATURE_FLAGS_KILL_SWITCH=checkout-v2,new-billing
```

## Dev marker

`is_dev = true` is a hint your app can use to hide a flag from non-engineering users or block production exposure. The package doesn't enforce anything. Your code decides what `is_dev` means.

The admin UI shows a `DEV` pill on rows where `is_dev = true`, and a per-key `DEV` button that flips the marker on every row sharing the key.

## Artisan commands

For CI/CD, seeding and ops scripts:

```bash
php artisan flag:list --scope=team-1        # table of keys + effective value
php artisan flag:create new-checkout --value=1 --scope=team-1 --hint="Q3 rollout"
php artisan flag:toggle new-checkout --scope=team-1
php artisan flag:delete 01J...              # by row id
php artisan flag:stale --days=30            # flags safe to retire (unchanged + constant)
php artisan flag:stale --days=30 --json     # JSON for CI
```

`flag:stale` reports flags untouched for N+ days that hold a single constant value across all
their rows (time-windowed flags are excluded) — your flag-debt cleanup list.

Generate a typed enum from the stored keys for IDE autocomplete and type-safe checks:

```bash
php artisan flag:generate --class="App\Features\AppFeature"
# writes app/Features/AppFeature.php implementing FeatureKey (--force to overwrite)
```

Keep flags in version control and reconcile them into the DB (config-as-code / GitOps):

```php
// feature-flags.php (config feature-flags.sync.path)
return [
    ['key' => 'new-checkout', 'value' => true, 'hint' => 'Q3'],
    ['key' => 'beta-search', 'value' => false, 'rollout_percentage' => 20],
];
```

```bash
php artisan flag:sync                 # upsert defined flags (idempotent)
php artisan flag:sync --dry-run       # show the plan, write nothing
php artisan flag:sync --prune         # also delete DB flags not in the file
```

All commands go through `FeatureFlagManager` (never Eloquent directly) and exit `1` on
not-found so scripts can react.

## Next steps

- [Scope resolvers](scopes.md)
- [Middleware guard](middleware.md)
- [Admin UI](admin-ui.md)
- [Recipes](recipes.md)

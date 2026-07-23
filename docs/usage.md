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

## Dev marker

`is_dev = true` is a hint your app can use to hide a flag from non-engineering users or block production exposure. The package doesn't enforce anything. Your code decides what `is_dev` means.

The admin UI shows a `DEV` pill on rows where `is_dev = true`, and a per-key `DEV` button that flips the marker on every row sharing the key.

## Next steps

- [Scope resolvers](scopes.md)
- [Middleware guard](middleware.md)
- [Admin UI](admin-ui.md)
- [Recipes](recipes.md)

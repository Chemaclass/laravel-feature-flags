# Usage

## Define flag keys with an enum

The `FeatureKey` contract lets you keep keys type-safe and centralized.

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

You can also pass raw strings everywhere — the enum is optional sugar.

## Check a flag

```php
use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;

$manager = app(FeatureFlagManager::class);

// Global check
$manager->isEnabled(AppFeature::NewDashboard);

// Scoped check — $scopeId is any string your app decides on
// (team id, region code, cohort name, customer id, etc.)
$manager->isEnabled(AppFeature::NewDashboard, $scopeId);

// String key also works
$manager->isEnabled('new-dashboard', $scopeId);
```

### Resolution rules

1. If a row exists for `(key, scope_id = $scopeId)` → use its `value`.
2. Otherwise if a row exists for `(key, scope_id = null)` (global) → use its `value`.
3. Otherwise → `false`.
4. Time-window: if `enabled_from`/`enabled_until` set, row only counts inside that window.

The scoped row **always wins over global** when both exist.

## List all flags for a scope

```php
$flags = $manager->all($scopeId);
// ['new-dashboard' => true, 'beta-billing' => false, ...]
```

Returns global flags merged with scope overrides (scope wins).

## Create / update a flag

```php
$manager->updateOrCreate(
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

## Toggle

```php
// Flip a single row by id
$newValue = $manager->toggleValue($flagId);

// Flip `is_dev` for every row sharing a key
$newDevState = $manager->toggleDevByKey('new-dashboard');
```

## Find

```php
$transfer = $manager->findById($id);                       // ?FeatureTransfer
$transfer = $manager->findByKeyAndScope('new-dashboard', $scopeId);
```

`FeatureTransfer` is a read-only DTO — safe to return from APIs.

## Time windows

Set `enabled_from` and/or `enabled_until` to gate by date:

```php
$manager->updateOrCreate(
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

`is_dev = true` is a hint your app can use to hide a flag from non-engineering users or block production exposure. The package doesn't enforce anything — your code decides what `is_dev` means.

Example: hide dev-only flags in the admin UI for non-admin users (customize the published Blade).

## Next steps

- [Scope resolvers](scopes.md)
- [Middleware guard](middleware.md)
- [Recipes](recipes.md)

# laravel-feature-flags

[![CI](https://github.com/Chemaclass/laravel-feature-flags/actions/workflows/ci.yml/badge.svg)](https://github.com/Chemaclass/laravel-feature-flags/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/chemaclass/laravel-feature-flags.svg)](https://packagist.org/packages/chemaclass/laravel-feature-flags)
[![Packagist Downloads](https://img.shields.io/packagist/dt/chemaclass/laravel-feature-flags.svg)](https://packagist.org/packages/chemaclass/laravel-feature-flags)
[![PHP](https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white)](composer.json)
[![Laravel](https://img.shields.io/badge/Laravel-11.x%20%7C%2012.x-FF2D20?logo=laravel&logoColor=white)](composer.json)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](.github/workflows/ci.yml)
[![Live demo](https://img.shields.io/badge/live-demo-6366f1?logo=readme&logoColor=white)](https://chemaclass.github.io/laravel-feature-flags/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A full feature-flag toolkit for Laravel that needs **nothing but your database**. Simple
on/off toggles when that's all you want — attribute targeting, percentage rollouts, A/B
variants, per-environment values, and a Blade admin UI when you need more.

> Drop it in any Laravel app. No external service, no vendor lock-in, no infra to run.

**▶ [Live demo & feature tour](https://chemaclass.github.io/laravel-feature-flags/)** — how it works, the admin UI, and every feature at a glance.

```php
if (FeatureFlag::isEnabled('new-dashboard')) {
    // ship it
}
```

## Install

```bash
composer require chemaclass/laravel-feature-flags
php artisan vendor:publish --tag=feature-flags
php artisan migrate
```

Auto-registers itself. Publishes the config, migration, admin view, and admin routes.
See [installation](docs/installation.md) for piece-by-piece publishing.

## Quickstart

**1. Create a flag** — from the admin UI at `/admin/feature-flags`, the CLI, or code:

```bash
php artisan flag:create new-dashboard --value=1
```

**2. Check it** anywhere:

```php
use Chemaclass\FeatureFlags\Facades\FeatureFlag;

if (FeatureFlag::isEnabled('new-dashboard')) {
    // gated code
}
```

**3. In Blade:**

```blade
@feature('new-dashboard')
    <x-new-dashboard />
@else
    <x-legacy-dashboard />
@endfeature
```

**4. Guard a route:**

```php
Route::get('/dashboard', DashboardController::class)
    ->middleware('feature.enabled:new-dashboard');
```

That's the whole loop. Everything below is optional power you can adopt when you need it.

## Cookbook

Each recipe is copy-paste; follow the link for the full reference.

<details>
<summary><b>Type-safe keys with an enum</b> (optional, recommended)</summary>

```php
use Chemaclass\FeatureFlags\Contracts\FeatureKey;

enum AppFeature: string implements FeatureKey
{
    case NewDashboard = 'new-dashboard';

    public function key(): string { return $this->value; }
}

FeatureFlag::isEnabled(AppFeature::NewDashboard);
```

Generate this enum from your existing flags: `php artisan flag:generate`. → [usage](docs/usage.md)
</details>

<details>
<summary><b>Per-scope overrides</b> (team, org, region, cohort, user…)</summary>

```php
// $scopeId is any string your app decides on. A scoped row beats the global one.
FeatureFlag::isEnabled('new-dashboard', $team->id);
```

A `FeatureScopeResolver` can resolve the scope automatically for the middleware and Blade.
→ [scopes](docs/scopes.md)
</details>

<details>
<summary><b>Target by attributes</b> (plan, country, email…)</summary>

```php
FeatureFlag::updateOrCreate(['key' => 'new-billing', 'scope_id' => null], [
    'value' => false,
    'rules' => [
        ['when' => [['attr' => 'plan', 'op' => 'eq', 'value' => 'pro']], 'then' => true],
    ],
]);

FeatureFlag::isEnabled('new-billing', $userId, ['plan' => 'pro']); // true
```

Operators: `eq, neq, in, not_in, gt, gte, lt, lte, contains, starts_with, ends_with`.
→ [usage](docs/usage.md)
</details>

<details>
<summary><b>Percentage rollout</b> (deterministic)</summary>

```php
FeatureFlag::updateOrCreate(['key' => 'new-checkout', 'scope_id' => null],
    ['value' => true, 'rollout_percentage' => 30]);
// enabled for a stable ~30% of scopes
```
→ [usage](docs/usage.md)
</details>

<details>
<summary><b>A/B variants + payloads</b></summary>

```php
$v = FeatureFlag::variant('homepage', $userId);
$v?->name;    // 'control' | 'blue'
$v?->payload; // per-variant blob
```
→ [usage](docs/usage.md)
</details>

<details>
<summary><b>Check many flags at once</b> (one query)</summary>

```php
FeatureFlag::allEnabled(['new-dashboard', 'beta-search'], $userId);
// ['new-dashboard' => true, 'beta-search' => false]
```
</details>

<details>
<summary><b>Environments, prerequisites, kill-switch, audit, real-time, caching</b></summary>

- **Environments** — same key, different value per env. → [usage](docs/usage.md)
- **Prerequisites** — a flag requires others to be on. → [usage](docs/usage.md)
- **Kill-switch** — force keys off via `FEATURE_FLAGS_KILL_SWITCH`. → [usage](docs/usage.md)
- **Audit log** — persist every toggle + admin history. → [extending](docs/extending.md)
- **Caching** — request memoization always on; set a cache store for cross-request + real-time invalidation. → [configuration](docs/configuration.md)
</details>

<details>
<summary><b>JSON API + JS client</b> (frontends & other services)</summary>

Enable the API (`feature-flags.api.enabled`), then evaluate flags over HTTP — targeting rules,
rollout and variants all apply:

```js
import { createFeatureFlags } from './vendor/feature-flags.js'; // php artisan vendor:publish --tag=feature-flags-js

const ff = createFeatureFlags({ endpoint: '/feature-flags/api/evaluate', scope: user.id, context: { plan: user.plan } });
await ff.load();
ff.isEnabled('new-dashboard'); // bool
ff.variant('homepage');        // { name, payload } | null
```
→ [api](docs/api.md)
</details>

<details>
<summary><b>Artisan & GitOps</b></summary>

```bash
php artisan flag:list --scope=team-1
php artisan flag:create beta-search --value=0 --hint="WIP"
php artisan flag:toggle new-dashboard
php artisan flag:stale --days=30         # flags safe to delete
php artisan flag:generate                # typed enum from your keys
php artisan flag:sync --prune            # reconcile from a versioned file (config-as-code)
php artisan flag:stats                   # exposure counts per flag/variant (analytics)
```
→ [usage](docs/usage.md)
</details>

## Admin UI

Visit `/admin/feature-flags` (configurable, gated by `web`+`auth` by default): flags grouped
by key, sliding toggles, inline editing, scope overrides, dark mode. → [admin-ui](docs/admin-ui.md)

## What you get

Everything runs on your existing database — no service to run, no vendor lock-in. In one package:

- **Evaluation** — boolean, per-scope overrides, attribute targeting rules, percentage rollout,
  time windows, prerequisites, and a global kill-switch.
- **Experiments & config** — weighted multivariate variants with per-variant payloads.
- **Environments** — the same key resolving differently per environment.
- **Delivery** — facade, `@feature` Blade directives, route middleware, Artisan commands, and a
  JSON HTTP API with a zero-dependency JS client for frontends and other services.
- **Operations** — request + cross-request caching with real-time invalidation, lifecycle
  events, an opt-in audit log with admin history, exposure analytics, stale-flag detection,
  typed enum codegen, and config-as-code sync.
- **Admin UI** — a published Blade page, no build step.

It's a self-hosted toolkit for a Laravel app — not a polyglot SaaS with an experimentation
stats engine and multi-language SDKs. If you need that, use a hosted platform. Evaluating
alternatives ([Pennant](https://laravel.com/docs/pennant), Unleash, Flagsmith, LaunchDarkly)?
Check their current docs — capabilities and pricing tiers change.

Already on Pennant? The optional [bridge](docs/pennant.md) lets `Feature::active()` resolve
through this package, so you can adopt it without rewriting call sites.

## Documentation

| Topic | Link |
|-------|------|
| Installation & setup | [installation.md](docs/installation.md) |
| Configuration reference | [configuration.md](docs/configuration.md) |
| Defining, checking, targeting & variants | [usage.md](docs/usage.md) |
| Scope resolvers | [scopes.md](docs/scopes.md) |
| Middleware guard | [middleware.md](docs/middleware.md) |
| HTTP API & JS client | [api.md](docs/api.md) |
| Exposure analytics | [analytics.md](docs/analytics.md) |
| Admin UI | [admin-ui.md](docs/admin-ui.md) |
| Custom repository, audit log, storage | [extending.md](docs/extending.md) |
| Laravel Pennant bridge | [pennant.md](docs/pennant.md) |
| Testing | [testing.md](docs/testing.md) |
| Recipes & patterns | [recipes.md](docs/recipes.md) |
| Architecture overview | [architecture.md](docs/architecture.md) |

## Stability

`1.0` and stable — semver from here on, **100% test coverage** enforced in CI. Pin with
`composer require chemaclass/laravel-feature-flags:^1.0`.

## Requirements

- PHP `^8.3`
- Laravel `^11.0 || ^12.0`

This package pulls in `illuminate/*` and nothing more. Any advisories from `composer audit`
come transitively from the Laravel framework your app already ships — keep Laravel on its
latest patch release to pick up fixes.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for local dev setup, the Docker demo app, the test
suites, formatting, and the `release.sh` flow.

## License

MIT.

# Contributing

Thanks for your interest. This guide covers everything needed to hack on the package locally: running the demo app, working with it while it's up, the test suites, and the conventions for commits and pull requests.

## Quick start

Two ways to boot a real Laravel app backed only by this package.

### Option A: Docker via Makefile (recommended)

Zero PHP setup on your machine. Requires Docker (24+).

```bash
make up           # build, start, wait for healthy, print URL
```

Then open <http://localhost:8000/admin/feature-flags>. The demo auto-logs you in as the seeded `demo@example.com` user.

All common dev tasks have shortcuts:

```bash
make help         # list every target
make up           # boot and wait until healthy
make down         # stop containers (keeps DB volume)
make restart      # restart running container
make logs         # tail logs
make shell        # sh inside the container
make tinker       # testbench tinker REPL
make seed         # reseed the demo DB
make reset        # wipe DB volume + boot fresh
make rebuild      # rebuild image (after Dockerfile/composer.json changes)
make test         # run pest (Unit + Feature)
make test-unit
make test-feature
make stan         # phpstan
```

Composer deps are baked into the image, so `make up` after `make down` is a few seconds, not a minute. The `workbench-db` named volume persists flag rows across restarts; use `make reset` to wipe.

### Option B: Raw `docker compose`

If you don't have `make`:

```bash
docker compose up -d --wait
docker compose run --rm test
docker compose run --rm stan
docker compose exec app vendor/bin/testbench tinker
docker compose down
```

### Option C: Native PHP

Requires PHP 8.3+, Composer, sqlite.

```bash
composer install
composer build      # vendor/bin/testbench workbench:build (creates the demo app skeleton + DB)
composer serve      # vendor/bin/testbench serve
```

Visit <http://localhost:8000>.

## The demo app

The repo ships a [Testbench](https://packages.tools/testbench) workbench: a tiny throwaway Laravel app under `workbench/` that boots only this package.

On first boot the seeder (`workbench/database/seeders/DatabaseSeeder.php`) creates:

| Email | Password | `tenant_id` |
|-------|----------|-------------|
| `demo@example.com` | `password` | `tenant-A` |

> `tenant_id` is just a demo column to showcase the bundled `UserTenantScopeResolver` example. The library has no opinion about what a scope is. See [docs/scopes.md](docs/scopes.md).

And these flags:

| Key | Scope | Value | Notes |
|-----|-------|-------|-------|
| `new-dashboard` | global | ON | Plain global flag |
| `beta-billing`  | global | OFF | Marked `is_dev` |
| `beta-billing`  | `tenant-A` | ON | Scope override beats global |
| `holiday-banner`| global | ON | Time-windowed (±14 days) |

A workbench middleware (`AutoLoginDemo`) silently authenticates every request as the demo user, so you never see a login screen.

## Working with the running demo

Once `make up` is done, you have a real, fully booted Laravel app on `localhost:8000` backed by the package. Here are the things you'll actually do while developing.

### 1. Daily workflow loop

```bash
make up                 # one-time: boot
# edit src/, workbench/, config/, resources/views/. Changes reload on next request
make logs               # in another terminal, watch requests + dd() output
make test-unit          # quick sanity (~0.3s)
make test-feature       # full HTTP path (~1.5s)
```

Source under `src/` and `workbench/` is bind-mounted into the container. Save the file, hit refresh, code reloads. No restart needed.

Config (`config/feature-flags.php`) is merged at boot, so changes there need `make restart`. Migrations need `make reset` (wipes the DB volume) or a manual `make shell` followed by `vendor/bin/testbench migrate`.

### 2. Use the admin UI

The UI lives at <http://localhost:8000/admin/feature-flags>. From there you can:

- Click **ON / OFF** to flip a flag value (calls `POST /admin/feature-flags/{id}/toggle`)
- Click **Delete** to remove a row (calls `DELETE /admin/feature-flags/{id}`)
- See every flag row grouped by key, with scope, dev marker, and time window

It's the same view your users will see once `feature-flags-views` is published. Edit `resources/views/admin/index.blade.php` and refresh; changes are live.

### 3. Inspect the admin endpoints from the outside

GET works with plain curl:

```bash
curl -s http://localhost:8000/admin/feature-flags | head -40
```

The mutation endpoints (`POST`, `DELETE`) sit in Laravel's `web` middleware group, so they require a CSRF token. That's by design: they are meant to be driven by the Blade admin UI or by your own authenticated frontend, not by anonymous curl.

For programmatic changes during local dev, use **tinker** (next section) which talks to the manager directly and bypasses HTTP entirely.

### 4. Drive the package from Tinker

```bash
make tinker
```

Inside the REPL you have the full Laravel container:

```php
// Resolve the manager
$m = app(\Chemaclass\FeatureFlags\Manager\FeatureFlagManager::class);

// Read
$m->isEnabled('new-dashboard');            // true
$m->isEnabled('beta-billing');             // false (global)
$m->isEnabled('beta-billing', 'tenant-A'); // true  (scope override)
$m->all('tenant-A');                       // ['new-dashboard' => true, 'beta-billing' => true, ...]

// Write
$m->updateOrCreate(
    ['key' => 'experiment-x', 'scope_id' => null],
    ['value' => true, 'hint' => 'tinker session'],
);

// Toggle
$row = \Chemaclass\FeatureFlags\Models\FeatureFlag::where('key','experiment-x')->first();
$m->toggleValue($row->id);
```

Hit refresh on the admin page to see your changes.

### 5. Test the middleware against a real route

Add a probe to `workbench/routes/web.php`:

```php
use Chemaclass\FeatureFlags\Http\Middleware\EnsureFeatureIsActive;

Route::get('/probe', fn () => 'allowed')
    ->middleware(EnsureFeatureIsActive::using('experiment-x'));
```

Then:

```bash
curl -i http://localhost:8000/probe   # 400 "Feature disabled" if off, 200 "allowed" if on
```

Flip `experiment-x` in the UI or via tinker and watch the response change.

### 6. Inspect the SQLite DB directly

```bash
make shell
sqlite3 workbench/laravel/database/database.sqlite \
  "select id, key, scope_id, value, is_dev from feature_flags;"
```

Or one-shot from the host:

```bash
docker compose exec app sqlite3 workbench/laravel/database/database.sqlite \
  "select * from feature_flags;"
```

### 7. Reseed without losing image cache

`make seed` re-runs the seeder against the live container. Use it after manual DB tinkering when you want a known-good baseline.

`make reset` is the heavier hammer: it drops the DB volume and reboots; all manual changes are gone.

### 8. Edit the demo seed data

Open `workbench/database/seeders/DatabaseSeeder.php`, add or change `updateOrCreate(...)` calls, then:

```bash
make seed         # if you want the new rows on top of existing data
make reset        # if you want a clean slate
```

### 9. Watch `dd()` and `dump()` output

`make logs` tails the dev server. Any `dd()` / `dump()` / `Log::info()` calls inside `src/` or `workbench/` show up there in real time.

### 10. Run a single test

Inside the container (`make shell`):

```bash
vendor/bin/pest tests/Feature/AdminUiTest.php
vendor/bin/pest --filter='toggle flips a flag value by id'
```

From the host without entering the container:

```bash
docker compose exec app vendor/bin/pest tests/Feature/AdminUiTest.php
```

## Customizing the demo

Everything under `workbench/` is package-dev only (excluded from the published package).

| File | Role |
|------|------|
| `workbench/app/Models/User.php` | Demo user model (auth target) |
| `workbench/app/Providers/WorkbenchServiceProvider.php` | Overrides admin middleware, registers `AutoLoginDemo` globally |
| `workbench/app/Http/Middleware/AutoLoginDemo.php` | Logs the demo user in on every request |
| `workbench/database/migrations/` | Demo-only migrations (e.g. `tenant_id` on users) |
| `workbench/database/seeders/DatabaseSeeder.php` | Initial flag + user data |
| `workbench/routes/web.php` | Demo routes (root redirect; add your probes here) |
| `workbench/bootstrap/providers.php` | Provider load order |
| `testbench.yaml` | Workbench boot config |

Rebuild the workbench skeleton after structural changes (new migration files, new providers, etc.):

```bash
make shell
vendor/bin/testbench workbench:build
exit
```

Or simply `make reset` to do it from a clean slate.

### Persisting vs. wiping demo data

- The `workbench-db` named volume keeps SQLite data across `make down`, `make restart`, `make up`.
- `make reset` deletes the volume and reseeds.
- The volume only exists in Docker mode. In native mode the file is `workbench/laravel/database/database.sqlite`.

### Live editing matrix

| You changed... | Action |
|---|---|
| `src/**/*.php` | Refresh browser. Done. |
| `workbench/routes/*`, `workbench/app/**/*.php` | Refresh browser. Done. |
| `resources/views/**/*.blade.php` | Refresh browser. Done. |
| `config/feature-flags.php` | `make restart` |
| `database/migrations/*`, `workbench/database/migrations/*` | `make reset` |
| `testbench.yaml`, `workbench/bootstrap/providers.php` | `make restart` |
| `composer.json`, `Dockerfile` | `make rebuild` |

## Running the tests

Two suites: **Unit** (manager + repository internals) and **Feature** (HTTP, middleware, admin UI end-to-end). Migrations run via Testbench's in-memory SQLite.

```bash
make test           # all suites
make test-unit
make test-feature
make stan           # phpstan
```

Native equivalents:

```bash
composer test
composer test:unit
composer test:feature
composer stan
```

### Writing new tests

- Add unit tests under `tests/Unit/`.
- Add feature tests under `tests/Feature/`. They get the full HTTP kernel, can hit routes, and use `actingAs($user)` for auth.
- All tests inherit from `Chemaclass\FeatureFlags\Tests\TestCase` automatically via `tests/Pest.php`.
- Prefer `mock()` over `Mockery::mock()`.

## Code conventions

- PHP `^8.3`
- `declare(strict_types=1);` at the top of every file
- `final readonly` classes by default for value-ish types
- DTO naming: `Transfer` for input DTOs, `Result` for handler return DTOs
- Type aliases (when used) prefixed `T`, never on DTOs
- No `interface` for plain types; use them for contracts/repositories
- Controllers never touch Eloquent; everything goes through repositories

## Static analysis

```bash
make stan
```

PHPStan level 6 against `src/` with `larastan/larastan` enabled (pinned as a dev dep). Must report `No errors`.

## Formatting

```bash
composer fmt           # apply Pint
composer fmt:check     # CI-style check, fails on drift
```

Configured in `pint.json` with the `laravel` preset plus `declare_strict_types`, alpha-sorted imports, and class-import collapsing.

## Releasing

Cutting a release is a single command:

```bash
./release.sh 0.2.0           # full release
./release.sh 0.2.0 --dry-run # preview, no changes
```

The script runs preflight (clean tree, on main, in sync with origin, tag free, CHANGELOG has notes), runs all quality gates, rewrites `CHANGELOG.md`, commits, tags, pushes, and creates the GitHub release with notes pulled from the changelog section. Requires `gh` CLI.

## Commits

Conventional Commits with these type rules:

- `feat:` new public feature
- `fix:` bug fix
- `ref:` refactor (we use `ref:`, not `refactor:`)
- `test:` tests only
- `docs:` documentation only
- `chore:` tooling, build, deps

Subject ≤ 50 chars. Body when the *why* isn't obvious. Group commits by context: one logical change per commit, not one mega-commit per branch.

Do not add `Co-Authored-By` or any AI tooling trailer.

## Pull requests

1. Branch from `main`.
2. Make small, focused commits.
3. Open a PR against `main`.
4. Reference issues with `Closes #N` to auto-close on merge.
5. Add labels (`bug`, `enhancement`, etc.) matching the change type.

CI runs the full test suite and PHPStan. Both must pass before merge.

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `make up` hangs forever | `make logs` to inspect; if composer install is still running on first build it can take a minute |
| `404` at `/admin/feature-flags` | Container not healthy yet. `docker compose ps` and look for `(healthy)` |
| `Target class [DatabaseSeeder] does not exist` | The build seed step ran against the wrong namespace; rerun `make seed` which uses the explicit `--class` |
| `Route [login] not defined` | `AutoLoginDemo` middleware is not registered. Confirm `Workbench\App\Providers\WorkbenchServiceProvider` is in `workbench/bootstrap/providers.php` |
| Port 8000 busy | Edit `docker-compose.yml`, change `"8000:8000"` to e.g. `"8080:8000"`, then `make rebuild` |
| Container can't write | Ensure your user owns the repo dir, then `make reset` |
| PHPStan memory crash | Already configured to `--memory-limit=512M` in the `stan` compose service; raise if you still hit it |
| Symfony deps require PHP 8.4 | Already pinned in the image. If you upgraded `composer.json`, `make rebuild` |

## Where to look next

- [docs/architecture.md](docs/architecture.md): internals overview
- [docs/testing.md](docs/testing.md): testing tips for consumers of the package
- [docs/extending.md](docs/extending.md): swap the repository or model
- [docs/recipes.md](docs/recipes.md): common patterns (gradual rollout, kill switch, Blade directive, etc.)

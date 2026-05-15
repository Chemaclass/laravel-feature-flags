# Contributing

Thanks for your interest. This guide covers everything needed to hack on the package locally: running the demo app, the test suites, and the conventions for commits and pull requests.

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

The repo ships a [Testbench](https://packages.tools/testbench) workbench, a tiny throwaway Laravel app under `workbench/` that boots only this package.

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

The workbench root route auto-logs you in as the demo user and redirects to the admin page.

### Customizing the demo

Everything under `workbench/` is package-dev only (excluded from the published package).

- Migrations: `workbench/database/migrations/`
- Seeders: `workbench/database/seeders/` (referenced in `testbench.yaml`)
- Demo routes: `workbench/routes/web.php`
- Demo user model: `workbench/app/Models/User.php`
- Boot config: `testbench.yaml`

Rebuild after structural changes:

```bash
composer build
```

### Resetting the demo DB

The workbench uses an on-disk SQLite file inside `workbench/laravel/database/`. Wipe it:

```bash
rm -rf workbench/laravel/database/*.sqlite
composer build
```

Or in Docker:

```bash
docker compose down -v
docker compose up
```

### Live editing

Source changes in `src/` and `workbench/` reflect on the next request. No rebuild needed; composer autoload is mounted from the host.

Config and migration changes need a rebuild:

```bash
composer build
```

## Running the tests

Two suites: **Unit** (manager + repository internals) and **Feature** (HTTP, middleware, admin UI end-to-end). Migrations run via Testbench's in-memory SQLite.

```bash
composer test           # all suites
composer test:unit
composer test:feature
composer stan           # phpstan
```

In Docker:

```bash
docker compose run --rm test
docker compose run --rm stan
```

### Writing new tests

- Add unit tests under `tests/Unit/`.
- Add feature tests under `tests/Feature/`. They get the full HTTP kernel, can hit routes, and use `actingAs($user)` for auth.
- All tests inherit from `Chemaclass\FeatureFlags\Tests\TestCase` automatically via `tests/Pest.php`.
- Prefer `mock()` over `Mockery::mock()`.

## Code style

- PHP `^8.3`
- `declare(strict_types=1);` at the top of every file
- `final readonly` classes by default for value-ish types
- DTO naming: `Transfer` for input DTOs, `Result` for handler return DTOs
- Type aliases (when used) prefixed `T`, never on DTOs
- No `interface` for plain types; use them for contracts/repositories
- Controllers never touch Eloquent; everything goes through repositories

## Static analysis

```bash
composer stan
```

PHPStan level 6 against `src/`. If you hit Eloquent magic-method errors, install [`larastan/larastan`](https://github.com/larastan/larastan) locally; it is not pinned as a dev dep but is recognized.

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
| `404` at `/` in workbench | Run `composer build` first |
| `SQLSTATE[HY000] [14] unable to open database file` | `mkdir -p workbench/laravel/database && composer build` |
| Port 8000 busy | Edit `docker-compose.yml`, change `"8000:8000"` to e.g. `"8080:8000"` |
| Container can't write | Ensure your user owns the repo dir; `docker compose down -v && docker compose up --build` |
| PHPStan memory crash | `vendor/bin/phpstan analyse --memory-limit=512M` |

## Where to look next

- [docs/architecture.md](docs/architecture.md): internals overview
- [docs/testing.md](docs/testing.md): testing tips for consumers of the package
- [docs/extending.md](docs/extending.md): swap the repository or model

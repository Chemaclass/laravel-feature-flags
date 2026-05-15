# Local Dev & Demo App

The repo ships a fully working **demo app** so you can run the feature-flag admin UI and play with flags without installing the package in a host project.

It's built on top of [Orchestra Testbench](https://packages.tools/testbench)'s workbench feature: a tiny throwaway Laravel app that boots only the package + workbench scaffolding under `workbench/`.

## Two ways to run

### Option A — Docker (recommended)

Zero PHP setup on your machine. Requires Docker (24+).

```bash
docker compose up
```

Once the build finishes:

- Demo app: <http://localhost:8000>
- Auto-login → redirect → `/admin/feature-flags`

Stop with `Ctrl+C`. The volume `composer-cache` survives between runs.

Run tests / static analysis in container:

```bash
docker compose run --rm test
docker compose run --rm stan
```

### Option B — Native PHP

Requires PHP 8.3+, Composer, sqlite.

```bash
composer install
composer build      # vendor/bin/testbench workbench:build (creates the demo app skeleton + DB)
composer serve      # vendor/bin/testbench serve
```

Visit <http://localhost:8000>.

## What the demo gives you

On boot, the seeder (`workbench/database/seeders/DatabaseSeeder.php`) creates:

| Email | Password | `tenant_id` column |
|-------|----------|--------------------|
| `demo@example.com` | `password` | `tenant-A` |

> `tenant_id` is just a demo column to showcase the bundled `UserTenantScopeResolver` example. The library itself has no opinion about what a scope is — see [scopes.md](scopes.md).

And these flags (showing all the moving parts):

| Key | Scope | Value | Notes |
|-----|-------|-------|-------|
| `new-dashboard` | global | ON | Plain global flag |
| `beta-billing`  | global | OFF | Marked `is_dev` |
| `beta-billing`  | `tenant-A` | ON | Scope override beats global |
| `holiday-banner`| global | ON | Time-windowed (±14 days) |

The workbench root route auto-logs you in as the demo user and redirects to the admin page.

## Admin UI walkthrough

Open <http://localhost:8000/admin/feature-flags> and you'll see the seeded flags. From the UI you can:

- Toggle ON/OFF a single row (`POST /admin/feature-flags/{id}/toggle`)
- Delete a row (`DELETE /admin/feature-flags/{id}`)
- Inspect scope, dev marker, and time window per row

To **create / update** a flag programmatically while the demo is running, use `php artisan tinker` (or a workbench command) and call `FeatureFlagManager::updateOrCreate(...)`.

## Customizing the demo

Everything under `workbench/` is package-dev only (excluded from the published package).

- Add migrations: `workbench/database/migrations/`
- Add seeders: `workbench/database/seeders/` and reference them in `testbench.yaml`
- Add demo routes: `workbench/routes/web.php`
- Swap the demo user model: `workbench/app/Models/User.php`
- Adjust the boot config: `testbench.yaml`

Rebuild the demo skeleton after structural changes:

```bash
composer build
```

## Resetting the demo DB

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

## Live editing

Source changes in `src/` and `workbench/` reflect on the next request — no rebuild needed. Composer autoload is mounted from the host.

Config + migration changes need a rebuild:

```bash
composer build
```

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `404` at `/` | Run `composer build` first |
| `SQLSTATE[HY000] [14] unable to open database file` | `mkdir -p workbench/laravel/database && composer build` |
| Port 8000 busy | Edit `docker-compose.yml` → change `"8000:8000"` to e.g. `"8080:8000"` |
| Container can't write | Ensure your user owns the repo dir; `docker compose down -v && docker compose up --build` |

## Next steps

- [Testing](testing.md) — write tests against the package in your own app
- [Architecture](architecture.md) — internals overview

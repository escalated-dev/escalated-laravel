# Escalated Laravel — Docker demo

A one-command demo of the `escalated-dev/escalated-laravel` package running inside a throwaway Laravel host app.

## Requirements

- Docker + Docker Compose
- ~500 MB disk space

## Run it

```bash
cd docker
cp .env.example .env          # optional — only needed if you want to change ports
docker compose up --build
```

Then open:

- **http://localhost:8000/demo** — click a seeded user to log in.
- **http://localhost:8025** — Mailpit UI for any outbound email the demo generates.

## What's inside

- **app** — PHP 8.3 + the package + a minimal Laravel 12 host app.
  The host app is at `docker/host-app/`. Its `composer.json` pulls the package
  from the repo root via a path repository, so edits to `../src/` show up on
  rebuild without publishing.
- **db** — Postgres 16 (alpine).
- **mailpit** — SMTP sink with a browser UI for eyeballing notification templates.

Every `docker compose up` starts fresh:

- `migrate:fresh` wipes the database
- `escalated:install` runs (config + migrations + permission seeder)
- `DemoSeeder` loads ~55 tickets, 10 users, departments, SLAs, macros, KB
  articles, and more so the UI is populated on first login.

## Seeded users

| Role     | Email                   | Password   |
|----------|-------------------------|------------|
| Admin    | alice@demo.test         | `password` |
| Agents   | bob / carol / dan @demo.test | `password` |
| Light    | ellie@demo.test         | `password` |
| Customers | frank / grace / henry / iris / jack @\*.example | `password` |

The `/demo` picker lets you one-click into any of them without typing a password.

## Resetting

```bash
docker compose down -v         # nothing is persisted; -v just cleans volumes
docker compose up --build      # if you changed package source or the host skeleton
```

## Scope

This is deliberately a dev/demo environment, not production infrastructure:

- Uses `php artisan serve` (single-process PHP server). Fine for clicking around.
- No nginx, no queue worker, no Redis.
- `QUEUE_CONNECTION=sync` — jobs run in-process.
- `APP_KEY` is a known static value so the image doesn't need a secret.
- `APP_ENV=demo` is required to expose the click-to-login routes. They hard-abort in any other environment.

If you need a production setup, this is not it — use Laravel Octane, FrankenPHP,
or your org's standard PHP deployment toolchain and wire Escalated in through
the normal install flow.

## Staff API conformance (CI)

`.github/workflows/conformance.yml` boots this stack with `ESCALATED_API_ENABLED=true`, runs `php artisan escalated:conformance-seed` inside the app container (host app only; it refuses to run unless `APP_ENV` is `demo` or `testing`, or `--force` is given), and runs the contract's black-box suite against `http://localhost:8080/support/api/v1`. The command prints the agent, admin and revocable tokens and the connect fixture as `KEY=value` lines, which the workflow masks and exports. Do not run it against a real install.

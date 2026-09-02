# Klaroly

Klaroly is a booking and payments product for makeup artists. This repository
holds the two halves that run in production:

| Directory | What it is | Runs on |
| --- | --- | --- |
| `api/` | Laravel 13 JSON API | Laravel Cloud, `api.klaroly.com` and `*.klaroly.com` |
| `app/` | Vue 3 single-page app, built with Vite | Cloudflare Workers, `app.klaroly.com` |

The marketing site is a separate repository.

Read `CLAUDE.md` before writing code. It holds the house rules and the data
constraints, and it is written for people as much as for tooling.

## Prerequisites

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.4 | Installed by [Laravel Herd](https://herd.laravel.com). `pdo_pgsql` must be loaded; check with `php -m`. |
| Composer | 2.x | Ships with Herd. |
| Postgres | 17 | [Postgres.app](https://postgresapp.com), listening on `127.0.0.1:5432`. |
| Node | 24 | |
| npm | 11 | Pinned in `app/package.json` under `packageManager`. Do not use pnpm or yarn. |

Herd does not put `php` on the PATH by default. Either enable that in Herd's
settings or add this to your shell profile:

```bash
export PATH="$HOME/Library/Application Support/Herd/bin:$PATH"
```

Postgres.app's command-line tools live in
`/Applications/Postgres.app/Contents/Versions/latest/bin`.

Nothing here uses Docker, Sail, Redis or a local mail server.

## API setup

Create the database once. The local role is your macOS username with no
password.

```bash
createdb klaroly
```

Then install and configure the API.

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

`.env.example` lists every variable with a comment. The defaults point at the
local Postgres above and log emails to `storage/logs/laravel.log` instead of
sending them.

Run the API on `localhost:8000`:

```bash
php artisan serve
```

Use `artisan serve` rather than a Herd `.test` domain for local work. The app
runs on `localhost:5173`, and the session cookie is `SameSite=Lax`, so the two
must be the same site (`localhost`) for cookie authentication to work.

Queued jobs use the database queue. When you need them processed locally, run a
worker in another terminal:

```bash
php artisan queue:listen
```

## App setup

```bash
cd app
npm install
cp .env.example .env
npm run dev
```

The dev server is on http://localhost:5173 and talks to the API at the
`VITE_API_URL` in `.env`, which defaults to `http://localhost:8000`.

## Running both together

Two terminals, from the repository root:

```bash
cd api && php artisan serve
```

```bash
cd app && npm run dev
```

Then open http://localhost:5173. Add a third terminal with
`php artisan queue:listen` when you are working on anything queued.

## Where environment variables come from

| Where | Local | Production |
| --- | --- | --- |
| API | `api/.env`, copied from `api/.env.example` | Set in the Laravel Cloud dashboard. Cloud injects the database and object storage variables itself. |
| App | `app/.env`, copied from `app/.env.example` | `app/.env.production`, which is committed. Vite bakes `VITE_*` values into the build, so there is no runtime configuration. |

Two rules that apply everywhere:

- The app's API base URL is always `VITE_API_URL`. It is never worked out from
  `window.location`.
- `VITE_TARGET` is set by the npm scripts, never by a `.env` file, so that the
  build target is always explicit.

## The two app build targets

| Script | Target | Service worker | Billing routes |
| --- | --- | --- | --- |
| `npm run dev`, `npm run build` | `web` | On | Included |
| `npm run build:mobile` | `mobile` | Off | Excluded from the bundle |

The mobile build is what Capacitor will wrap later. Capacitor is not installed
yet; do not add it until there is a working login screen and one list view.

## Tests and linting

API, from `api/`:

```bash
php artisan test
```

```bash
composer lint
```

App, from `app/`:

```bash
npm run typecheck
```

```bash
npm run lint
```

## Deploying

The API deploys from Laravel Cloud on push, with `api/` as the application's
root directory.

The app deploys to Cloudflare Workers as static assets. Sign in once with
`npx wrangler login`, then:

```bash
npm run ship
```

`ship` builds the web target and runs `wrangler deploy` using `wrangler.jsonc`.

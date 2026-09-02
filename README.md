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

Herd does not put `php`, `composer` or `herd` on the PATH unless you switch
that on in Herd's settings. Either do that, or add this to your shell
profile:

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
php artisan migrate --seed
```

`--seed` loads the system message and contract templates and one demo
account, "Ellie Marsh Makeup", whose owner logs in as `ellie@example.com`
with the password in `DEMO_PASSWORD` (`password` by default).

`.env.example` lists every variable with a comment. The defaults point at the
local Postgres above and log emails to `storage/logs/laravel.log` instead of
sending them.

Serve the API with Herd. Run this once, from `api/`:

```bash
herd link klaroly
```

Herd then serves the API at http://api.klaroly.test, and at every other
`*.klaroly.test` subdomain, which mirrors production. There is nothing to
start: Herd is always running.

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

Have Herd give the dev server a hostname under the same parent domain as
the API. Run this once:

```bash
herd proxy app.klaroly http://127.0.0.1:5173
```

The app is then at http://app.klaroly.test whenever `npm run dev` is running.
The shared parent domain matters: the session cookie is `SameSite=Lax`, so the
app and the API must be the same site for cookie sign-in to work, locally as
in production.

## Running both together

Herd serves the API on its own. Start the app:

```bash
cd app && npm run dev
```

Then open http://app.klaroly.test. Add a second terminal with
`php artisan queue:listen` from `api/` when you are working on anything
queued.

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

The API tests run against a real Postgres database, not SQLite, because the
check constraints and partial indexes are part of what is tested. Create it
once:

```bash
createdb klaroly_test
```

Then, from `api/`:

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

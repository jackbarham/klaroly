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
| PHP | 8.5 | Installed by [Laravel Herd](https://herd.laravel.com). `pdo_pgsql` must be loaded; check with `php -m`. |
| Composer | 2.x | Ships with Herd. |
| Postgres | 17 or later (18 works) | [Postgres.app](https://postgresapp.com), listening on `127.0.0.1:5432`. |
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

Run the app's unit tests with:

```bash
npm test
```

**Where things live in `app/src`.**

| Folder | What is in it |
| --- | --- |
| `lib/` | Everything that is not a component: `api.ts` (the only caller of `fetch`), `auth.ts`, `platform.ts`, `navigation.ts` (the one list of destinations, used by both navigations), `form.ts` (the one way a screen submits a form), `dialog.ts` (the focus trap every modal shares), `monthGrid.ts` and `dayMarks.ts` (the calendar's arithmetic), `bookingFixtures.ts` (temporary, see below), and `testMount.ts` and `testHelpers.ts`, which tests use to mount and drive a component |
| `stores/` | Pinia. `auth.ts` is the only way a screen reads or changes who is signed in; `bookings.ts` is the only way a screen reads the calendar's events |
| `router/` | One explicit routes array. Everything behind the sign-in is a child of the layout route |
| `components/layout/` | The app shell: `AppLayout`, `AppSidebar`, `AppTabBar`, `CreateMenu`, `SettingsNav` |
| `components/ui/` | PageHeader, Card, EmptyState, AppButton, IconButton, Sheet, Icon, StatusPill, ListRow, DataTable, SectionBand. Registered globally by `components/kit.ts`, so no screen imports them |
| `components/bookings/` | The bookings screen: `MonthGrid` (presentational, and it has never heard of a booking), `BookingsCalendar`, `MonthJumpSheet`, `BookingList`, `BookingRow`. Not in the global kit, because they belong to one screen |
| `components/form/` | FormSection, FormField, FormActions, FormError, RadioCard and the controls, also global. FormField owns the label, hint, error and id wiring; the controls do not |
| `views/` | One file per page. Pages that are not built yet share `PlaceholderView.vue` |
| `types/` | The shapes the API returns: `auth.ts` for the signed-in person, `bookings.ts` for a calendar event |
| `locales/` | `en-GB.json`. Every user-facing string is a key here |
| `assets/app.css` | The `@theme` block, which is where every colour, font, radius and spacing step is defined |

Two rules worth knowing before you open any of it: a component or a view
never calls the API (it goes through a store, and `src/lib/boundary.test.ts`
enforces that), and the shell is greyscale (the `--color-neutral-*` tokens
only), because the brand work has not landed yet.

**The bookings screen still reads fixtures, and the API it will read is
built.** `GET /api/events` and `GET /api/events/months` exist and are tested;
the app has not been switched over yet, so `src/lib/bookingFixtures.ts` is
still what `src/stores/bookings.ts` calls. It exports a single
`loadBookingEvents()` and no component may import it
(`src/lib/bookings.guards.test.ts` fails if one does), so switching over is
that function body plus the field names, which move from camelCase to the
snake_case the rest of the API speaks. `CLAUDE.md` covers both halves.

**Signing in locally.** With the API seeded (`php artisan db:seed`), open
http://app.klaroly.test/login and sign in as `ellie@example.com` with the
password in the API's `DEMO_PASSWORD` (`password` by default). The demo
account is already verified, so the home page shows no banner. To see the
whole flow, use "Create an account" with a fresh address: the home page then
shows the verification banner, and the verification email, like the
password reset email, is written to `api/storage/logs/laravel.log`. Copy the
link out of the log into the same browser. The verification link goes to the
API and comes back to the app with `?verified=1`; the reset link opens the
app's `/reset-password` page directly.

## Running both together

Herd serves the API on its own. Start the app:

```bash
cd app && npm run dev
```

Then open http://app.klaroly.test. Add a second terminal with
`php artisan queue:listen` from `api/` when you are working on anything
queued.

## Authentication

The API signs users in two ways: a session cookie for the web app and a
bearer token for the mobile app. Both are described in `CLAUDE.md` under
"Authentication shape".

**From the web app.** Open http://app.klaroly.test and sign in as the demo
account, `ellie@example.com`, with the password in `DEMO_PASSWORD`
(`password` by default). The app fetches `/sanctum/csrf-cookie` and posts
to `/login` on the API; the session cookie is set on `.klaroly.test`, so it is
sent to every `*.klaroly.test` host afterwards.

**With curl against the token endpoint.** This is what the mobile app does.

```bash
curl -s -X POST http://api.klaroly.test/api/auth/token -H "Accept: application/json" -H "Content-Type: application/json" -d '{"email":"ellie@example.com","password":"password","device_name":"curl"}'
```

The response carries `token`, `expires_at` and `me`. Use the token on every
later request:

```bash
curl -s http://api.klaroly.test/api/me -H "Accept: application/json" -H "Authorization: Bearer PASTE_THE_TOKEN_HERE"
```

Revoke it with `DELETE /api/auth/token` and the same header.

**Registering from the mobile app.** Fortify's `/register` needs the CSRF
cookie, so the mobile app uses the stateless twin at `/api/auth/register`.
It takes the same fields plus `device_name` and answers exactly as the
token endpoint does, with a 201:

```bash
curl -s -X POST http://api.klaroly.test/api/auth/register -H "Accept: application/json" -H "Content-Type: application/json" -d '{"business_name":"Nina Okoro Makeup","name":"Nina Okoro","email":"nina@example.com","password":"correct-horse-battery","password_confirmation":"correct-horse-battery","device_name":"curl"}'
```

The other twins are `/api/auth/forgot-password`, `/api/auth/reset-password`
and `/api/auth/email/verification-notification`. Each mirrors the Fortify
route of the same name; see the route tables in `CLAUDE.md`.

**Where the emails go.** With `MAIL_MAILER=log`, which `.env.example` sets,
the verification email sent at registration and the password reset email
are written to `storage/logs/laravel.log`. Search the log for the link: the
verification link points at the API and redirects to the web app once
clicked; the reset link points straight at the web app's `/reset-password`
page with the token and email in the query string.

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

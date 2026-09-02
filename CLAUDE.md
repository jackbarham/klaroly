# Klaroly

Read this file fully before changing anything. It exists so that nobody has to
be told these rules twice, and so that a competent developer who has never seen
the project can pick it up.

Klaroly is a booking and payments product for makeup artists. This repository
has two deployable halves:

- `api/` is a Laravel 13 JSON API. It deploys to Laravel Cloud and serves
  `api.klaroly.com` and `*.klaroly.com`.
- `app/` is a Vue 3 single-page app built with Vite. It deploys to Cloudflare
  Workers as static assets and serves `app.klaroly.com`. The same code is
  built a second way for the mobile app (see build targets below).

The marketing site is a separate repository and is not part of this one.

`README.md` covers getting from clone to running. This file covers how to
write code here.

## House rules

- Two-space indentation everywhere, with one exception: PHP is four spaces
  because Laravel Pint's default preset enforces PSR-12. Run `composer lint`
  in `api/` and `npm run lint` in `app/` before committing.
- No semicolons at the end of JavaScript or TypeScript lines. ESLint enforces
  this.
- British English in every user-facing string and in every comment.
  Colour, organise, cancelled, licence.
- No emoji anywhere in the codebase. Not in strings, comments, commit
  messages or documentation.
- Plain, obvious code over clever code. The project has a handover goal. If
  a reader would need to know a trick to follow the code, write it the long
  way and add a comment.
- npm, not pnpm or yarn. Every `package.json` carries a `packageManager`
  field pinning the npm version.
- No script is named `deploy`. The script that ships a build is `ship`.
- Do not add Docker, Laravel Sail, Redis, Horizon or a local mail server.
  Laravel Cloud provides managed queues and does not support Horizon.

## Data constraints

These are decided. Do not revisit them in code without a conversation first.

- The account is the tenant. Every customer-data table carries `account_id`,
  including tables that also carry `booking_id`. A user is never a tenant;
  users belong to accounts.
- All money is stored as bigint pence in columns with a `_pence` suffix.
  Never float, never decimal, never a currency string.
- Event dates and times are stored as local wall clock plus an IANA timezone
  name, never as UTC. A wedding at 2pm in Manchester is `14:00` plus
  `Europe/London`.
- Scheduled reminders are the opposite: computed to a UTC instant when they
  are scheduled, and recomputed if the event moves.
- Audit, signature and financial timestamps are UTC without exception.
- Enum-like columns are `varchar` with a check constraint, never a Postgres
  enum type.
- No enum anywhere lists makeup services. The rate card is rows in a table.
- Laravel Cashier's billable model will be the account, not the user. For
  that reason Cashier's stock migrations, which add Stripe columns to
  `users`, have not been published. Write the billing tables against
  accounts as part of the schema work.

## Authentication shape

- Bearer tokens on mobile, session cookie on web, from the start.
- The API uses Fortify for the authentication routes and Sanctum for both the
  SPA cookie session and mobile personal access tokens. `statefulApi()` is on
  in `bootstrap/app.php`.
- Session cookie: `SESSION_DOMAIN` is `.klaroly.test` locally and
  `.klaroly.com` in production. `SameSite=Lax` always. Secure in production
  only.
- CORS allows exactly three origins, read from `CORS_ALLOWED_ORIGINS`:
  `https://app.klaroly.com`, `capacitor://localhost` and the local dev app
  (`http://app.klaroly.test` under Herd, `http://localhost:5173` without).
  Never hardcode an origin in `config/cors.php`.

## API rules (`api/`)

- Laravel 13 on PHP 8.4 with Postgres. Pest for tests, not PHPUnit. Pint with
  the default preset.
- Packages in use: Fortify, Sanctum, Cashier, Resend, DomPDF,
  spatie/icalendar-generator, Sentry.
- The locale directory is `lang/en-GB/`. No user-facing string may be a
  literal in a controller, a Blade template, a mail class or a notification.
  Use translation keys only.
- Key names describe meaning, not content: `booking.deposit_due`, never
  `booking.your_deposit_is_due_soon`.
- Config comes from `.env`. `.env.example` lists every variable with a
  one-line comment; keep it complete when you add one.
- `APP_TIMEZONE=UTC`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`.
- The API has no front-end build. There is no `package.json` in `api/`.

## App rules (`app/`)

- Vue 3 with `<script setup>` and TypeScript, built by Vite. Pinia for state.
  Tailwind CSS 4. vue-i18n. Dexie is installed for later offline work and is
  unused so far.
- Single-file component block order is `<template>`, then `<script setup>`,
  then `<style>` if one is needed. Every component, without exception. This
  is the opposite of the Vue tooling default, so ESLint's `vue/block-order`
  rule is set to `['template', 'script', 'style']`.
- Vue Router with an explicit routes array in `src/router/index.ts`. No
  file-based routing. Do not install `unplugin-vue-router`.
- Tailwind 4 is configured CSS-first: `@import "tailwindcss"` and one
  `@theme` block in `src/assets/app.css`. There is no `tailwind.config.js`
  and there must not be one. The palette, font stack and spacing scale are
  `@theme` custom properties. The default Tailwind palette is switched off,
  so nothing outside the theme block can use a hardcoded colour or spacing
  value. This matches the marketing site, so both share one way of defining
  tokens.
- No UI component framework of any kind. Not Ionic, not a Tailwind component
  library.
- Every user-facing string is a key in `src/locales/en-GB.json`, with the
  same naming rule as the API.
- Never derive the API base URL from `window.location`. It is always
  `import.meta.env.VITE_API_URL`.
- Local hosts are served by Laravel Herd: the API at `api.klaroly.test`
  (and any `*.klaroly.test`), the app at `app.klaroly.test`, which Herd
  proxies to the Vite dev server. Both sit under one parent domain so the
  session cookie works exactly as it does in production.

### `src/lib/platform.ts`

The single place any native-versus-web branch may live. It exports
`isNative`, `isIOS`, `isAndroid` and `isWeb`. Nothing else in the codebase
checks the platform directly: no user-agent sniffing, no `Capacitor.` calls
outside this file. Until Capacitor is added, native means the mobile build
target.

### `src/lib/api.ts`

The single wrapper around `fetch`. On web it sends the session cookie and the
CSRF header. On native it sends a bearer token. Callers pass a path and get
JSON back; they never know which kind of credential was sent, and they never
call `fetch` themselves.

### The two build targets

`VITE_TARGET` is `web` or `mobile`. It is set by the npm scripts in
`package.json`, never by a `.env` file, so the choice is always explicit.

| Target | Service worker | Billing routes |
| --- | --- | --- |
| `web` | On, via `vite-plugin-pwa` | Included |
| `mobile` | Off | Excluded from the bundle |

Exclusion is done in `vite.config.ts` by defining the compile-time constant
`__WEB_TARGET__`. In `src/router/index.ts` the billing route is added inside
`if (__WEB_TARGET__)` using a dynamic `import()`. On the mobile build the
constant is the literal `false`, the branch is dead code, and the billing
chunk is never emitted. A static top-level import would keep the code in the
mobile binary, which is the thing being avoided. Follow the same pattern for
anything else that is web-only.

The service worker is registered by a script the PWA plugin injects into
`index.html`, so no code in `src/` mentions it and the mobile build has
nothing to strip.

### Cloudflare

`wrangler.jsonc` describes an assets-only Worker named `klaroly-app` serving
`./dist` with `not_found_handling` set to `single-page-application`.
`npm run ship` builds the web target and deploys it.

### Capacitor

Not installed yet. Do not run `cap add ios` or `cap add android` until there
is a login screen and one working list view. `npm run build:mobile` already
produces the bundle Capacitor will wrap.

## Current state

Structure only. There is no business logic, no migrations have been run, and
the only screens are a login placeholder and an empty dashboard behind a
router guard. The database schema is a separate piece of work that changes
the stock users migration, so leave `database/migrations` alone until then.

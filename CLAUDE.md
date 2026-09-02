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
- All money is stored as a bigint integer in the currency's ISO 4217 minor
  unit, in columns with a `_minor` suffix (decision 77). Pence, cents and
  euro cents are all the minor unit; the column never says which. Never
  float, never decimal, never a currency string. Every money column sits
  beside a `currency` column or inherits one from its booking or account,
  and `App\Casts\MoneyCast` exposes it as an `App\Support\Money`.
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

- Bearer tokens on mobile, session cookie on web, from the start. The
  `sanctum` guard accepts either, so one API route serves both.
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
- Every request from the app sends `Accept: application/json`. Fortify only
  answers in JSON when asked to; without the header it redirects as if to a
  Blade app.

### Routes

Fortify registers its routes at the root, with no prefix, inside the group
from `config/fortify.php`: `web`, then `NormaliseEmail`, then
`ThrottleForgotPassword`.

| Method | Path | Middleware | Notes |
| --- | --- | --- | --- |
| POST | `/login` | guest, throttle:login | Returns `{"two_factor": false}` |
| POST | `/logout` | auth:web | 204 |
| POST | `/register` | guest | 201, logs the browser in |
| POST | `/forgot-password` | guest, throttle:forgot-password | Same 200 for known and unknown addresses |
| POST | `/reset-password` | guest | Token and email come from the emailed link |
| GET | `/email/verify/{id}/{hash}` | auth:web, signed, throttle | The link in the verification email; redirects to `FRONTEND_URL` |
| POST | `/email/verification-notification` | auth:web, throttle | Resend |
| PUT | `/user/profile-information` | auth:web | |
| PUT | `/user/password` | auth:web | Revokes every token and every other session |
| GET | `/sanctum/csrf-cookie` | web | Sanctum; the web app calls it before its first POST |

Fortify also registers the two-factor, password-confirmation and passkey
routes because those features are configured. They are unused and no screen
exposes them.

Hand-written routes live in `routes/api.php` under `/api`:

| Method | Path | Middleware | Notes |
| --- | --- | --- | --- |
| POST | `/api/auth/token` | NormaliseEmail, throttle:token | Email, password, device_name. Returns the plain-text token, its expiry and the same payload as `/api/me` under `me` |
| POST | `/api/auth/register` | NormaliseEmail, throttle:register | Everything `/register` accepts plus device_name. 201 with the token endpoint's shape; no session |
| POST | `/api/auth/forgot-password` | NormaliseEmail, throttle:forgot-password | Email. Same 200 body as `/forgot-password`, known or unknown |
| POST | `/api/auth/reset-password` | NormaliseEmail | Token, email, password, password_confirmation. 200 `{message}`; a bad token is a 422 on `email`. Issues no token |
| GET | `/api/usernames/{username}` | throttle:30,1 | `{available, reason}` where reason is `invalid`, `reserved`, `taken` or null |
| GET | `/api/me` | auth:sanctum, account | User, current account, membership and the feature map |
| GET | `/api/auth/tokens` | auth:sanctum, account | The caller's tokens, with the current one marked |
| DELETE | `/api/auth/tokens/{id}` | auth:sanctum, account | The caller's own only; 404 otherwise |
| DELETE | `/api/auth/token` | auth:sanctum, account | Revokes the token making the request; 400 for a session caller |
| POST | `/api/auth/email/verification-notification` | auth:sanctum, account, throttle:6,1 | 202 and the email is sent; 204 if already verified |

### The rules

- **Every authenticated API route sits in the `['auth:sanctum', 'account']`
  group.** `account` is `App\Http\Middleware\BindCurrentAccount`, which
  resolves the user's account through `App\Services\AccountResolver`
  (`last_account_id` if they still belong to it, otherwise their first
  membership by id), binds it as `CurrentAccount` and saves `last_account_id`
  when it changed. A user with no membership gets a 403. Nothing else binds
  the tenant for a request.
- **Email is normalised on the way in.** `NormaliseEmail` lowercases and trims
  the `email` input on every Fortify route and on the token endpoint, and
  `CreateNewUser` and `UpdateUserProfileInformation` do it again before
  validating. The `lower(email)` index is the backstop, not the mechanism.
- **Four Fortify routes have stateless JSON twins under `/api/auth`**
  (decision 87): register, forgot-password, reset-password and
  email/verification-notification. Fortify's routes sit in the `web` group
  and need the CSRF cookie, which a bearer-token caller and a Capacitor
  WebView cannot supply. The twins run outside the `web` group, start no
  session, and reuse Fortify's actions (`CreateNewUser`,
  `ResetUserPassword`), the password broker and Fortify's response
  bindings, so the two paths answer identically. Fortify's own routes are
  unchanged and remain what the web app uses. Login has no twin because
  `POST /api/auth/token` is the mobile login. Profile and password update
  twins arrive with the settings screen.
- **One place mints a token.** `App\Services\TokenIssuer` sets the device
  name, abilities and expiry, and builds the `{token, expires_at, me}`
  payload. The token endpoint and the register twin both call it.
- **One credential check.** `App\Services\PasswordAuthenticator` is used by
  `Fortify::authenticateUsing` and by the token endpoint. A wrong email and a
  wrong password get the same 422 on the `email` field.
- **Registration is `App\Actions\Fortify\CreateNewUser`**, one transaction
  that creates the user, the account, its settings row (features from
  `config/features.php`), the owner membership and the username history row.
  A username is derived from the business name when none is given.
- **Mobile tokens** are named after the device, expire after
  `sanctum.token_expiry_days` (365), and `sanctum.expiration` is the same
  length in minutes so a token created anywhere still expires.
  `sanctum:prune-expired` runs daily.
- **Passwords**: `Password::defaults()` in `AppServiceProvider` is the only
  policy (ten characters plus the breach check, which is off in testing).
  Changing a password revokes every token and every other session; resetting
  one revokes every token and every session.
- **Email verification is sent, not enforced** (decision 83). The `verified`
  alias goes on the authenticated group in `routes/api.php` when enforcement
  arrives, and nowhere else.
- **Browser-facing links point at the web app.** `app.frontend_url` (from
  `FRONTEND_URL`) is the reset-password link, the page a verified user lands
  on, and where a logged-out browser is sent from a Fortify route. API routes
  never redirect; they answer 401 in JSON.

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

The database schema, email-and-password authentication and the mobile
twins of Fortify's registration, password reset and verification routes
exist.
`docs/database-schema.md` is the specification: when a table or column is
in doubt, that document wins, and a change to the schema is made there
first. Section 7 of it designs tables that are deliberately not migrated
yet (`scheduled_messages`, `client_links`, `signing_events`, `intake_forms`,
`intake_questions`, `feedback_responses`, `media`, `push_tokens`,
`activities`, `booking_transfers`).

The 22 tables migrated, in dependency order: `users` (the stock migration,
modified in place), `accounts`, `account_settings`, `account_user`,
`identities`, `username_history`, `contacts`, `services`, `bookings`,
`events`, `party_members`, `booking_contacts`, `booking_lines`, `quotes`,
`invoices`, `payments`, `notes`, `message_templates`, `contract_templates`,
`agreements`, `entitlements`, `booking_user`. A final migration adds the
three foreign keys that could not be declared inline, a later one adds
the `users.marketing_consent_source` check constraint, and another gives
`account_settings.deposit_percent` a default of 25 so a bare insert passes
the deposit rule check (decision 90). Framework tables from
Sanctum, Fortify and the passkeys package are untouched. Cashier's own
migrations are not published; its four columns sit on `accounts`, and the
subscription tables arrive with the billing work.

What sits on top of the tables:

- `App\Enums`: one string-backed enum per enum-like column, each with a
  `checkConstraintSql()` helper so the database constraint is generated from
  the enum and the two cannot drift. `FeatureKey` holds the nine feature
  toggles from decision 78; a key absent from an account's map is off, so
  registration writes the full default map from `config/features.php`.
- `App\Support\Money` and `App\Casts\MoneyCast`: every `_minor` column
  is read and written as a value object. No float ever touches a price.
- `App\Support\CurrentAccount` and `App\Models\Concerns\BelongsToAccount`:
  the tenancy scope. With no current account bound, scoped queries return
  nothing and creating throws. `User`, `Account`, `Identity` and
  `UsernameHistory` are not scoped. `MessageTemplate` and `ContractTemplate`
  also show system rows with a null `account_id`. On a request, the
  `account` middleware does the binding (see "Authentication shape").
- `App\Services\BookingPricing` (the only place totals are computed),
  `App\Services\InvoiceNumbering` (the only place an invoice gets a number),
  `App\Services\Features` (the only reader of feature toggles),
  `App\Services\PasswordAuthenticator` (the only credential check),
  `App\Services\AccountResolver` (the only place a user's account is chosen)
  and `App\Services\TokenIssuer` (the only place a token is minted).
- `App\Rules\Username` with `config/reserved_usernames.php`. Its
  `reasonFor()` is the single check behind both registration and
  `GET /api/usernames/{username}`.
- Config that is not the framework's: `config/billing.php` (trial length),
  `config/features.php` (the default feature map), `config/demo.php` (the
  demo password), `config/reserved_usernames.php`. Nothing outside
  `config/` reads `env()`.
- Factories for every model, `SystemDefaultsSeeder` (system message and
  contract templates) and `DemoAccountSeeder` (the "Ellie Marsh Makeup"
  account, which doubles as the App Store review account; owner login
  `ellie@example.com` with the password from `DEMO_PASSWORD`).
- Pest tests in `api/tests` run against a real Postgres database named
  `klaroly_test`, because the check constraints and partial indexes are part
  of what is tested. Create it once with `createdb klaroly_test`. The
  authentication tests live in `tests/Feature/Auth`.

Not built yet: passkeys and two-factor enforcement (configured, unused),
Sign in with Apple or Google, switching between accounts, collaborator
invitations, account deletion, client login, and billing. The web app still
has only a login placeholder and an empty dashboard behind a router guard;
the authentication screens are the next piece of work.

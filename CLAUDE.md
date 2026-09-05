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
  one revokes every token and every session. Both send the queued
  `App\Notifications\PasswordChanged` email from inside the action, so the
  web routes and the mobile twins cannot differ. Its strings live in
  `lang/en-GB/mail.php`, one group per notification class.
- **Email verification is sent, not enforced** (decision 83). The `verified`
  alias goes on the authenticated group in `routes/api.php` when enforcement
  arrives, and nowhere else.
- **Browser-facing links point at the web app.** `app.frontend_url` (from
  `FRONTEND_URL`) is the reset-password link, the page a verified user lands
  on, and where a logged-out browser is sent from a Fortify route. API routes
  never redirect; they answer 401 in JSON.

## API rules (`api/`)

- Laravel 13 on PHP 8.5 with Postgres. Pest for tests, not PHPUnit. Pint with
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
- There is one grey family and one accent colour, and that is the whole
  palette. `--color-neutral-0` through `--color-neutral-900` are what new
  work is built from, with `--color-surface` for the page itself.
  `--color-brand` is the primary action and nothing else: `AppButton`'s
  primary variant applies it, and the tab bar's create button is the one
  hand-rolled twin of that button, so a screen never decides for itself that
  something deserves colour. Never add a colour outside the theme block, and
  never reach for the brand colour to make something stand out.
- There is deliberately no danger or success token. A form says what is wrong
  in words, and shows it with a heavier border and a heavier weight: an
  invalid control is `border-2 border-neutral-900` plus `aria-invalid`, and a
  form-level failure is `FormError`, which uses the same treatment. Colour on
  its own tells a screen reader nothing, so it was never what carried the
  meaning, and one way of saying "this is wrong" beats one per screen. When a
  semantic red and green are wanted they arrive with the brand work, as a
  text, border, background and hover tint each, checked for contrast.
- Spacing comes off an eight pixel grid. Use Tailwind steps 2, 4, 6, 8, 10,
  12, 16, 20 and 24 for padding, margins and gaps. Step 1 is allowed only
  inside a control, such as between an icon and its label. Heights, widths and
  positions are not on that list because a control has to be the size it has
  to be, but keep them on the same grid.
- `env(safe-area-inset-*)` cannot live in `@theme`, so `src/assets/app.css`
  defines five utilities with `@utility` and they are the only place an inset
  is read: `page-top`, `page-bottom` (clears the tab bar), `bar-bottom` (where
  the tab bar floats), `sheet-bottom` and `above-bar` (a sticky row of form
  actions). Compose them with Tailwind variants, for example
  `max-lg:page-bottom`.
- A component and a view never talk to the API. They read and change state
  through a Pinia store; the store calls `src/lib/auth.ts`, which calls
  `src/lib/api.ts`. The single exception is `ApiError`, which a screen may
  import so that it can tell a rate limit from a lost connection.
  `src/lib/boundary.test.ts` reads the source of every file under
  `src/components` and `src/views` and fails if anything else appears, because
  the point of the rule is what happens when nobody is looking.
- No UI component framework of any kind. Not Ionic, not a Tailwind component
  library. There is a small kit of the app's own in `src/components/ui` and
  `src/components/form`, described below.
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

### `src/lib/navigation.ts`

Where someone can go in the app, written down once. One array, `navigation`,
with a key, a route name, a locale key for the label, an icon name, a flag for
the phone tab bar and which half of the sidebar the entry belongs to. The
create action is in the array with a null route name, in the position it is
drawn in, which is why the tab bar reads Home, Bookings, the create button,
Enquiries, More.

The derived lists (`tabBarItems`, `sidebarMain`, `sidebarSecondary`,
`moreItems`, `createItem`) and the two functions that work out what is current
(`sectionKey`, `activeTabKey`, `activeTabIndex`) all come from that array.
**Neither navigation component may contain a list of destinations**, and
adding a section is a line in this file rather than an edit in three places.
`settingsGroups` does the same job for the ten groups of settings.

`sectionKey` is what makes a detail page mark its list: `/bookings/42` marks
Bookings, every settings page marks Settings. Sections the tab bar has no room
for are reached through More, so on a phone they mark More.

### The app shell

`src/components/layout/AppLayout.vue` is the shell every signed-in page sits
in, and it is a route with children in `src/router/index.ts`, so it mounts
once and a navigation swaps only the page inside it. It holds the skip link,
one `<main>`, one `<RouterView>` and the create sheet's open state, which is
the only state the shell has.

Which navigation shows is decided by Tailwind's `lg` variant and nothing else.
There is no width watched in JavaScript and no user agent read. Both
navigations are in the DOM at every width and the hidden one is
`display: none`, so it is out of the accessibility tree as well as off the
screen.

- `AppSidebar.vue`, at `lg` and up: a fixed column that does not collapse,
  with the New button at the top and sign out at the bottom. It never links
  to More.
- `AppTabBar.vue`, below `lg`: a bar that floats clear of the bottom edge,
  with a raised create button in the middle that is not a destination. The
  pill behind the current item is one element that is measured and moved with
  a transform, never a style on each item and never an animated layout. It is
  measured on the first render, so a deep link lands with it in the right
  place, and again on a route change and on a resize.
- `CreateMenu.vue` with `ui/Sheet.vue`: one component, two presentations. A
  bottom sheet on a phone, a menu anchored under the sidebar's New button at
  `lg`. The anchor is plain CSS offsets that match the geometry at the top of
  the sidebar; both files carry the arithmetic in a comment, so if one moves
  the other has to.
- `SettingsNav.vue`: the second column of settings links, shown beside a
  settings page at `lg`. The settings index never shows it, because the index
  is that list.

### The UI kit and the form kit

`src/components/ui` is PageHeader, Card, EmptyState, AppButton, IconButton,
Sheet and Icon. `AppButton` is the only button component in the app: anything
that looks like a button is that with a different variant or size. `Icon` is
the only place an icon lives, as a list of SVG paths on a 24 by 24 grid,
stroked with `currentColor`. There is no icon package and there will not be
one.

`src/components/form` is FormSection, FormField, FormActions, FormError and
the controls.
**FormField owns every piece of wiring around a control**: it generates the
id, ties the label to it, puts the hint and the error into
`aria-describedby` in that order and sets `aria-invalid`. The controls take
`id`, `labelledBy`, `describedBy` and `invalid` and do none of that
themselves, so a field is written as one line:

```vue
<FormField v-slot="field" :label="..." :hint="..."><TextInput v-bind="field" v-model="x" /></FormField>
```

A control that is not a labelable element, which is the toggle switch and the
radio group, is named by `aria-labelledby` pointing at the field's label.

Two things sit outside that four-prop shape, both for a check that happens
while someone types rather than when they submit. `TextInput` takes a
`status` of `valid` or `invalid` and draws a tick or a cross inside its right
edge; `FormField` takes a `statusMessage` and announces it in an `sr-only`
live region beside the hint and the error. The mark is inside the control's
box, so the control draws it; the words belong with the field's other
messages, so the field says them. Pass the pair or pass neither: a mark with
no message is a shape nobody can hear. The register screen's username check
is the one user of both.

`FormError` is the other half of saying no: a failure that belongs to no
single field, such as a rate limit or a lost connection. `FormField` handles
the per-field kind and this is the rest.

Neither kit validates anything itself. What a screen does with a rejected
submit is settled and is the same on every one: a 422's field messages go
into `FormField`'s `error`, anything else goes into `FormError`, and
`focusFirstInvalid` from `src/lib/form.ts` then moves focus to the first
control carrying `aria-invalid`. The authentication screens are the worked
example.

### The style guide

`docs/style-guide.md` is the specification for every visual decision, in the
same way `docs/database-schema.md` is for the data. When a colour, size, space,
radius, border, shadow or duration is in doubt, that document wins, and a change
to any of them is made there first. `docs/style-guide-screens/` is what each
component should look like in both themes.

**It is staged, not applied.** Its tokens live in `docs/tokens.css` and have not
replaced `src/assets/app.css`. Until the restyle lands, the palette described
under App rules above is what the app actually uses, and that description is the
current truth. Do not part-apply the style guide: the theme block and every
component that references it move in one change.
`docs/kitchen-sink-reference.html` is a standalone render of the target system;
delete it once `/kitchen-sink` reflects the same thing.

Once the restyle has landed, these are the rules:

- Every colour, size, space, radius, border, shadow and duration comes from the
  semantic tokens in `src/assets/app.css`. Never hardcode a value and never use
  a Tailwind arbitrary value in square brackets.
- If something you need has no token, stop and ask rather than inventing one.
- **A token added or changed is three edits in one commit**: the theme block in
  `src/assets/app.css`, the token table in `docs/style-guide.md`, and
  `/kitchen-sink`. They do not drift apart. Change the control radius once and
  every button, input, select and menu in the app and on that page moves
  together, because they all read the same variable.
- Every component works in light and dark.
- **Every new component is added to `/kitchen-sink` in the same change that
  creates it**, in every variant and state it supports. A component that is not
  on that page is not finished.

### `/kitchen-sink`

A route that renders the whole UI kit and form kit on one page, in every
variant, size and state, in both themes. It exists to be looked at: it is how a
change to a token is judged, and how a new component is reviewed.

It is a development page and it is removed before launch. It makes no API calls,
owns no state beyond the local state its own demos need, and is not linked from
any navigation.

### `src/lib/api.ts`

The single wrapper around `fetch`. On web it sends the session cookie and the
CSRF header, fetches `/sanctum/csrf-cookie` before a non-GET when the
`XSRF-TOKEN` cookie is absent, and retries a non-GET exactly once after a
419. On native it sends the bearer token from `src/lib/tokenStorage.ts`.
Callers pass a path and get JSON back; they never know which kind of
credential was sent, and they never call `fetch` themselves. A non-2xx
answer throws `ApiError`, whose `validationErrors()` maps a 422 to field
names. A 401 from any request calls the one handler registered with
`onUnauthenticated()`, which the auth store uses to mark the person signed
out.

### `src/lib/auth.ts`

The one module that knows web signs in with a session and native signs in
with a token. It imports `isNative` and `deviceName` from `platform.ts` and
is the only file besides `api.ts` that may branch on the platform. It
exports `signIn`, `register`, `signOut`, `fetchMe`, `forgotPassword`,
`resetPassword`, `resendVerification` and `checkUsername`, each choosing the
Fortify route or its `/api/auth` twin. Every `Me` that arrives, from
`/api/me` or embedded in a token response, goes through one helper that
turns an empty-array `notification_preferences` into an object. Screens
never import it: they call the Pinia store in `src/stores/auth.ts`, and the
store calls this.

### `src/lib/tokenStorage.ts`

`get()`, `set(token)` and `clear()` for the native bearer token. The
implementation is in memory, so a mobile build forgets its login on reload,
which is accepted until Capacitor arrives. When it does, this file is
replaced with Capacitor's secure storage and nothing else changes.

### Testing

Vitest with happy-dom. Test files sit beside the file they test with a
`.test.ts` suffix and run with `npm test`. `fetch` is mocked with `vi.fn` on
`globalThis`; there is no HTTP mocking library and no component testing
library. To test the native branch, `vi.mock('@/lib/platform')` in that
test file, which is the approved way and the only way. `vitest.config.ts`
is separate from `vite.config.ts` because the latter insists on a build
target.

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

The service worker is registered by `src/lib/updates.ts`, the one file in
`src/` that mentions it. `main.ts` loads it, and `App.vue` loads the
`UpdateBar` component, with the dynamic-import pattern above, so the mobile
bundle contains neither. The plugin runs in `prompt` mode: a newer build
installs and waits, the module checks for one hourly and when the tab comes
back into view, and the bar offers a reload rather than reloading under
someone mid-form.

### Cloudflare

`wrangler.jsonc` describes an assets-only Worker named `klaroly-app` serving
`./dist` with `not_found_handling` set to `single-page-application`.
`npm run ship` builds the web target and deploys it.

### Capacitor

Not installed yet. Do not run `cap add ios` or `cap add android` until there
is a login screen and one working list view. `npm run build:mobile` already
produces the bundle Capacitor will wrap.

Two things about the shell will need attention on the day it is installed,
both because the tab bar is `position: fixed`:

- **The iOS keyboard.** In a WebView the bar rides up and sits on top of the
  keyboard, over the field being typed into. The fix is the `Keyboard` plugin
  with `resize: 'native'`, and hiding the bar while the keyboard is open.
  That is a piece of state that cannot be derived from the width, so it is
  the first honest reason to add a small UI store, and the branch belongs in
  `src/lib/platform.ts` like every other native check.
- **Android insets.** `env(safe-area-inset-*)` reports zero in an Android
  WebView unless the native shell asks for an edge-to-edge layout, and the
  bar would then sit under the gesture pill. `viewport-fit=cover` is already
  in `index.html`, which is the web half of the same job.

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

The app has its authentication screens: sign in, register (with a live
username preview and availability check), forgot password, reset password,
sign out, the verification banner with resend, the verified landing on the
home page, and session restore on load. They are built from the UI kit and
the form kit like every other screen, so they are also the only forms in the
app that really submit something. The router guard awaits
`auth.bootstrap()` once before the first navigation, sends a signed-out
visitor to `/login?redirect=` and follows that redirect after sign-in only
when it is a relative path.

It also has its shell, and every route behind the sign-in exists as a page.
That is all it has: the shell is furniture, not features. **Nothing in it
calls the API.** The only request the app makes when it loads is still the
one `GET /api/me` that `auth.bootstrap()` sends, and there is no invented
data anywhere: a page that has not been built says so.

- The routes, all children of the layout route: `/`, `/bookings`,
  `/bookings/:id`, `/enquiries`, `/enquiries/:id`, `/contacts`,
  `/contacts/:id`, `/more`, `/account`, `/help`, `/settings` and its ten
  groups, plus `/billing` on the web target. A detail route echoes its `:id`
  and looks nothing up.
- Every page that has not been built is one shared `PlaceholderView.vue`: a
  header and a card saying so. A route names its title with `meta.titleKey`,
  which is also the document title, and where a phone's back link goes with
  `meta.backTo`. When a section is really built it gets a view of its own and
  the routes array points at that instead.
- The pages that are real: `HomeView` (the greeting, the verification banner
  and an empty state), `MoreView` (the phone's overflow list and sign out),
  the settings index, and `/settings/travel`, which is the one honest example
  of the form kit doing a section's work. It saves nothing: there is no
  settings API yet.
- The document title comes from the route's locale key, there is a skip link
  to `<main>`, every route has one `<h1>` in it, and both navigations are
  real `<nav>` landmarks.

Vitest covers what will actually break: the navigation config's derived
lists and its idea of what is current, that both navigations render one item
per entry and mark the right one, that the pill resolves on a deep link,
the sheet's open, close and focus behaviour, FormField's wiring, that a
button says it is busy while its request is in flight, that a rejected field
puts the message on that field and moves focus to it, that a second submit
sends nothing while the first is still going, that the username check is
announced in words as well as drawn, and the two tests that read the source
of the app rather than run it:
`boundary.test.ts`, which stops business logic leaking into components, and
`router/routeNames.test.ts`, which fails if any route name written down
anywhere is not a route that exists. Renaming a route is the change that
breaks a `router.push` in a screen nobody opened, and a route name is a
string, so nothing else can catch it. Component tests mount through
`src/lib/testMount.ts`, twenty lines of `createApp` with the real router,
i18n and pinia, because there is no component testing library and there is
not going to be one.

The device list, profile and password change screens are not built.

Not built yet: passkeys and two-factor enforcement (configured, unused),
Sign in with Apple or Google, switching between accounts, collaborator
invitations, account deletion, client login, billing, and persistent token
storage on native (see `src/lib/tokenStorage.ts`).

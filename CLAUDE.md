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
- A comment says why, never what the next line already says. A docblock that
  restates a method's name is deleted, not kept for symmetry.
- The second copy of a helper is the moment it moves: to `src/lib` or
  `src/components/form/field.ts` in the app, to `tests/Pest.php` or a service
  in the API. Two files carrying the same ten lines is how the previous
  passes drifted.
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
| PUT | `/user/password` | auth:web | Keeps the session making the request; revokes every token and every other session |
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
| PUT | `/api/user/profile-information` | auth:sanctum, account, NormaliseEmail, throttle:profile-update | Name and email. Runs Fortify's own action, so a changed email un-verifies and sends a fresh verification email. 200 with the `/api/me` payload |
| PUT | `/api/user/password` | auth:sanctum, account, throttle:password-update | Current password plus the new one. 200 `{message}`; no `me`, because nothing in it changed |
| PATCH | `/api/account` | auth:sanctum, account | The business name. Owner only; a collaborator gets 403. 200 with the `/api/me` payload |
| PUT | `/api/user/marketing-consent` | auth:sanctum, account | `consented`. 200 with the `/api/me` payload |
| GET | `/api/events` | auth:sanctum, account | `from` (defaults to today) and `to` (unbounded when omitted). The events in the range, each carrying its booking's stage, client, total and waiting-on state |
| GET | `/api/events/months` | auth:sanctum, account | No parameters. Every month the account holds an event in, for all time, as `["2026-09", ...]` |

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
  `CreateNewUser`, `UpdateUserProfileInformation` and `PasswordAuthenticator`
  call its `normalise()` again on their own input, so there is one definition
  of a normalised address. The `lower(email)` index is the backstop, not the
  mechanism.
- **Six Fortify routes have stateless JSON twins** (decision 87): register,
  forgot-password, reset-password and email/verification-notification under
  `/api/auth`, and profile and password update at
  `/api/user/profile-information` and `/api/user/password`. Fortify's routes
  sit in the `web` group and need the CSRF cookie, which a bearer-token
  caller and a Capacitor WebView cannot supply. The twins run outside the
  `web` group, start no session, and reuse Fortify's actions
  (`CreateNewUser`, `ResetUserPassword`, `UpdatesUserProfileInformation`,
  `UpdatesUserPasswords`, the last two resolved from the container), the
  password broker and Fortify's response bindings, so the two paths answer
  identically. Fortify's own routes are unchanged and remain what the web app
  uses. **The two settings twins keep Fortify's own paths on purpose**: a
  twin is the same route without the session, and one under a different name
  invites the question of whether it also behaves differently. Login has no
  twin because `POST /api/auth/token` is the mobile login.
- **A Fortify route the app depends on gets a parity test.** One test asserts
  that the web path and its `/api` twin answer the same way, and
  `tests/Feature/Account/WebRouteParityTest.php` is where they live. The
  reason is not symmetry: the two paths share one action, so a change made
  for the phone lands on the browser as well, and Fortify's own routes have
  no tests of their own. Changing `UpdateUserPassword` to fix the mobile
  password change left the whole suite green while proving nothing about
  `PUT /user/password` at all.
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
  `App\Services\PasswordChanger` is the one place a password is replaced:
  it saves the hash, revokes every token and every session except the ones it
  is told to keep, and sends the queued `App\Notifications\PasswordChanged`
  email. `UpdateUserPassword` keeps the credential that made the request, the
  session on the web and the token on the phone, so changing your password on
  a device does not sign that device out; `ResetUserPassword` keeps neither,
  because a reset is asked for by someone who could not sign in. Both actions
  call it, so the web routes and the mobile twins cannot differ. Its strings
  live in `lang/en-GB/mail.php`, one group per notification class. **A
  validation rule that reads a guard only works for one of our two
  credentials**, which is what `current_password:web` turned out to be: the
  rule below is the worked example, and the same trap waits in any rule that
  asks a guard a question instead of asking the model.
- **The current password is checked against the user, not a guard.**
  Laravel's `current_password` rule asks a named guard for its user, and the
  `web` guard is a guest on a bearer-token request, so it would reject every
  password change made from the phone. `UpdateUserPassword` checks the `User`
  it was handed instead, which answers the same for a session caller, a token
  caller and a caller with no request at all. A user with no password, which
  provider sign-in will produce, is told so rather than compared against
  null.
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
- **An authenticated write is throttled when it checks a credential or sends
  an email, and not otherwise.** Both are things an attacker can spend on
  someone else's behalf: a credential check is a guessing game, and an email
  is a message to a real inbox. Everything else is a write to a row the
  caller already owns, and a limiter on it buys nothing while adding a
  failure mode. The four My Account routes are the worked example.
  `PUT /api/user/password` qualifies on the first and
  `PUT /api/user/profile-information` on the second, because changing an
  email queues a verification message; each has a named limiter beside the
  others in `FortifyServiceProvider`. `PATCH /api/account` and
  `PUT /api/user/marketing-consent` qualify on neither and carry no limiter,
  which is the decision and not an oversight.
- The API has no front-end build. There is no `package.json` in `api/`.

## App rules (`app/`)

- Vue 3 with `<script setup>` and TypeScript, built by Vite. Pinia for state.
  Tailwind CSS 4. vue-i18n. Dexie is installed for later offline work and is
  unused so far.
- Single-file component block order is `<template>`, then `<script setup>`,
  then `<style>` if one is needed. Every component, without exception. This
  is the opposite of the Vue tooling default, so ESLint's `vue/block-order`
  rule is set to `['template', 'script', 'style']`.
- Props are typed with `defineProps<{ ... }>()`. An optional prop is `name?:`
  and nothing more: a boolean is false when absent and anything else is
  `undefined`, which is what the type already says. `withDefaults` is used
  only for a default that means something, such as a button's variant. ESLint's
  `vue/require-default-prop` is switched off for that reason; it would want
  `labelledBy: undefined` written under every control.
- A component that wraps a native element declares no event the element
  already fires. `AppButton` has no `click` emit: the parent's `@click` falls
  through to the `<button>` as a native listener.
- Vue Router with an explicit routes array in `src/router/index.ts`. No
  file-based routing. Do not install `unplugin-vue-router`.
- Tailwind 4 is configured CSS-first, in `src/assets/app.css`: an
  `@import "tailwindcss"`, two `@theme` blocks and the `:root` and `.dark`
  value sets. There is no `tailwind.config.js` and there must not be one. The
  default Tailwind palette and font stacks are switched off, so nothing
  outside the theme block can use a hardcoded colour or spacing value. This
  matches the marketing site, so both share one way of defining tokens.
- **The palette is two layers and a component only ever touches the second.**
  Layer one is the primitives: the raw ramps, `--color-neutral-*`,
  `--color-accent-*`, the status hues, the translucent whites the dark theme
  is built from. Nothing outside the theme block may name one. Layer two is
  the semantics, declared with `@theme inline` so that each utility points at
  a variable rather than at a resolved value: `surface`, `surface-sunken`,
  `surface-hover`, `surface-raised`, `surface-overlay`, `surface-disabled`,
  `scrim`, `text`, `text-strong`, `text-muted`, `text-subtle`,
  `text-placeholder`, `text-on-accent`, `border`, `border-strong`,
  `border-focus`, `accent`, `accent-hover`, `accent-text`, `accent-subtle`
  and the four status families. A screen writes `bg-surface-raised` or
  `text-text-muted`, and never a primitive, never a hex.
- **Dark mode is those two layers and nothing else.** `:root` and `.dark` give
  the semantic names different values; the primitives never move. **There is
  no `dark:` variant anywhere in the app and there must not be one.** Wanting
  to write one means the element is still holding a raw colour, and the token
  is the fix. The class is never set: the theme is wired and deliberately not
  exposed yet.
- `accent` is the primary action and nothing else: `AppButton`'s primary
  variant applies it, and the tab bar's create button is the one hand-rolled
  twin of that button, so a screen never decides for itself that something
  deserves colour. `accent-text` is the accent as words, and it is a separate
  token because a fill that can be read on white cannot be read on the dark
  ground.
- A form says what is wrong in words first. An invalid control is
  `border-2 border-danger` plus `aria-invalid`, and a form-level failure is
  `FormError`, which uses the same heavy border with `text-danger-text`. The
  border and the words carry the meaning and the colour helps, because colour
  on its own tells a screen reader nothing. `TextInput`'s live status mark is
  the same rule in miniature: the tick is `success-text` and the cross is the
  `danger-text` the error message uses, and the field says which in words
  beside it. `StatusPill` reads all four families, and My account
  is the first screen to use two of them: `warning` on an unverified email
  address and `info` on the device you are reading from. `danger` outside a
  form and the booking states are still to come.
- Spacing comes off an eight pixel grid. Use Tailwind steps 2, 4, 6, 8, 10,
  12, 16, 20 and 24 for padding, margins and gaps. Steps 1 and 3 are the two
  half-steps the design allows, and both are written down in
  `docs/style-guide.md`: 4px inside a control, and 12px for a control's icon
  gap and for a menu panel's padding. Heights, widths and positions are not on
  that list because a control has to be the size it has to be, but keep them
  on the same grid.
- `env(safe-area-inset-*)` cannot live in `@theme`, so `src/assets/app.css`
  defines six utilities with `@utility` and they are the only place an inset
  is read: `page-top`, `page-bottom` (clears the tab bar), `bar-bottom` (where
  the tab bar floats), `sheet-bottom`, `above-bar` (a sticky row of form
  actions) and `stick-top` (where a sticky block comes to rest). Compose them
  with Tailwind variants, for example `max-lg:page-bottom`.
  **A sticky block uses `stick-top`, never `top-0`**, which tucks under the
  status bar the moment a native shell asks for an edge-to-edge layout, which
  `viewport-fit=cover` in `index.html` already sets the app up for. It also
  reads `--stick-offset`, for the second sticky block on a screen that has to
  rest under the first rather than behind it; whoever sets that offset owns
  measuring it.
- The focus ring is a sixth `@utility`, `focus-ring`, written as
  `focus-visible:focus-ring` on anything that takes focus and has no edge of
  its own to recolour (a button, a link, a navigation item, a checkbox), and
  as `peer-focus-visible:focus-ring` on the radio card. It reads
  `--border-width-focus` and `--border-focus`, so every ring in the app is one
  rule. A control with a visible edge recolours that edge instead, through
  `edgeClasses` in `src/components/form/field.ts`.
- `check` and `radio` are the seventh and eighth, and they are the tick box
  and the radio drawn rather than left to the browser. A native control takes
  `accent-color` and nothing else, so its mark is the platform's: heavy, and
  close enough to the edges of a 20px box that the box reads as a solid
  block. `check` gives the 20px box its border, its radius and, when checked,
  a 14px tick centred in it as a background image; `radio` makes the same box
  round and swaps the tick for a dot. A checkbox is `class="check"` and a
  radio is `class="check radio"`, which is the style guide's `.k-check` and
  `.k-radio`. The tick's colour is written as white because a background
  image cannot read a variable, and `--text-on-accent` is `--color-white` in
  both themes, so there is no second value it could need.
- A component and a view never talk to the API. They read and change state
  through a Pinia store; the store calls `src/lib/auth.ts`, which calls
  `src/lib/api.ts`. The single exception is `ApiError`, which a screen may
  import so that it can tell a rate limit from a lost connection.
  `src/lib/boundary.test.ts` reads the source of every file under
  `src/components` and `src/views` and fails if anything else appears, because
  the point of the rule is what happens when nobody is looking.
  `src/lib/styleRules.test.ts` reads the same files for a `dark:` variant, a
  Tailwind arbitrary value or a hex colour, for the same reason.
- No UI component framework of any kind. Not Ionic, not a Tailwind component
  library. There is a small kit of the app's own in `src/components/ui` and
  `src/components/form`, described below. **The kit is registered globally**
  by `src/components/kit.ts`, which `main.ts` and the test mounter both
  install, so a screen writes `<AppButton>` or `<FormField>` without an
  import. `src/components/global.d.ts` declares the same list for vue-tsc, so
  a wrong prop on a global component is still a type error; a component is
  added to both files, and to `/kitchen-sink`, in the same change, and a
  component whose props change goes back to the kitchen sink in that change
  too. Inside the
  kit a component still imports the sibling it uses, so each one is complete
  on its own. Everything outside the kit, the shell, `AuthCard`, the banners,
  is imported where it is used, because each of those belongs to one place.
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
`isNative`, `isIOS`, `isAndroid`, `isWeb` and `deviceName`, which names the
token a mobile login asks for. Nothing else in the codebase
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
`settingsGroups` and `accountGroups` do the same job for the two sections that
are themselves a list of pages: the ten groups of settings and the four pages
of My account. Both are `SectionGroup[]`, and both are read by the section's
index, by `SectionNav` and by the route record that names the section, so a
page is added in one place.

`sectionKey` is what makes a detail page mark its list: `/bookings/42` marks
Bookings, every settings page marks Settings and every My account page marks
My account. Sections the tab bar has no room for are reached through More, so
on a phone they mark More. **A section with pages under it needs a line in
`sectionKey`**, or its children resolve to nothing: no sidebar mark, and an
`activeTabIndex` of minus one, which hides the tab bar's pill with no error
anywhere.

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
- `SectionNav.vue`: the second column of links beside a page in a section
  that is itself a list of pages, at `lg` and up. Settings and My account are
  both that shape, so it is one component given a `groups` array, an
  `indexRouteName` and a `labelKey`. It renders nothing on its section's own
  index, because the index is that list, which is why it takes the index route
  name at all.
- `SectionLayout.vue` (in `src/views`, because it is a routed component): the
  frame that holds `SectionNav` and the `RouterView` beside it. Both sections
  use it, and the three things that differ are static `props` on the route
  record in `src/router/index.ts`, which is already the one place every
  destination is written down. There is no per-section layout file.

### The UI kit and the form kit

`src/components/ui` is PageHeader, Card, EmptyState, AppButton, IconButton,
Sheet, Icon, StatusPill, ListRow, DataTable and SectionBand. `AppButton` is
the only button component in the app: anything that looks like a button is
that with a different variant or size. A click on it, or on `IconButton`, is
the native event on the root element; neither declares an event of its own.
`Icon` is the only place an icon lives, as a list of SVG paths on a 24 by 24
grid, stroked with `currentColor`, and it exports `iconNames` so the kitchen
sink draws the set from the same list. There is no icon package and there
will not be one.

`src/components/form` is FormSection, FormField, FormActions, FormError,
RadioCard and the controls: TextInput, TextArea, SelectInput, CheckboxInput,
RadioGroup, ToggleSwitch, DateInput and MoneyInput.
**FormField owns every piece of wiring around a control**: it generates the
id, ties the label to it, puts the hint and the error into
`aria-describedby` in that order and sets `aria-invalid`. The controls take
`id`, `labelledBy`, `describedBy`, `invalid` and `disabled`, which is the
`ControlProps` interface in `src/components/form/field.ts`, and do none of that
themselves, so a field is written as one line:

```vue
<FormField v-slot="field" :label="..." :hint="..."><TextInput v-bind="field" v-model="x" /></FormField>
```

A control that is not a labelable element, which is the toggle switch and the
radio group, is named by `aria-labelledby` pointing at the field's label.

**`FormField` has a second shape, `inline`, and the tick box is the whole
reason for it.** A checkbox reads as the sentence beside it rather than as a
heading with a box underneath, so with `inline` the field's label becomes the
row, wraps the control and carries `optionRowClasses`: the words sit beside
the box, in the same 44px hit area, with the same hover. The label names the
box through its own `for`, so the slot hands the control no `labelledBy` in
this shape, and a hint and an error still hang below the row like any other
field's. `CheckboxInput` is therefore only ever a box; it used to carry a
label of its own for the times it stood outside a field, and one control
written two ways is one control that lines up two ways. Every checkbox in the
app is a field:

```vue
<FormField v-slot="field" inline :label="..."><CheckboxInput v-bind="field" v-model="x" /></FormField>
```

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
into `FormField`'s `error`, anything else goes into `FormError`, and focus
moves to the first control carrying `aria-invalid`. That rule is written once,
as `useSubmit` in `src/lib/form.ts`: a screen puts `ref="form"` on its form,
binds the `pending`, `errors` and `formError` it returns, and calls
`submit()` with the request it wants made. A screen with something of its own
to do with a failure passes a second function, which sees the `ApiError`
first and returns true when it has dealt with it. The register screen is the
worked example for sending a rejection back to the step that can show it, and
`AccountDetailsView` for a form making two requests, where the handler has to
say which one failed without implying the other did. The two generic messages, `common.too_many_attempts`
and `common.request_failed`, are the only strings the helper knows.

### The style guide

`docs/style-guide.md` is the specification for every visual decision, in the
same way `docs/database-schema.md` is for the data. When a colour, size, space,
radius, border, shadow or duration is in doubt, that document wins, and a change
to any of them is made there first. `docs/style-guide-screens/` is what each
component should look like in both themes.

**It is applied.** `src/assets/app.css` carries the two token layers and every
component reads them, so the rules below are live rather than aspirational.
`docs/tokens.css` is the record of where the system came from; the app's own
theme block is what actually runs, and the two differ in one deliberate place,
which is `--radius-card`.

Control heights, the type scale and the button size ramp have not moved yet.
The app is on the new palette and still on Tailwind's own sizes.

The rules:

- Every colour, size, space, radius, border, shadow and duration comes from the
  semantic tokens in `src/assets/app.css`. Never hardcode a value and never use
  a Tailwind arbitrary value in square brackets.
- If something you need has no token, stop and ask rather than inventing one.
- **A token added or changed is three edits in one commit**: the theme block in
  `src/assets/app.css`, the token table in `docs/style-guide.md`, and
  `/kitchen-sink`. They do not drift apart. Change the control radius once and
  every button, input, select and menu in the app and on that page moves
  together, because they all read the same variable.
- **A token the guide specifies stays, even while nothing reads it.** The type
  scale, the spacing levers, the container widths and the solid `success`,
  `warning` and `info` fills are in the theme ahead of the screens that need
  them, and the kitchen sink says "not used yet" beside each one. **That note
  is part of the token's entry and moves when the token is first used**: My
  account put the subtle and text halves of all four status families to work
  through `StatusPill`, and the entries say what uses them now. Removing one is a
  style-guide change first. A variable that is in neither the guide nor a
  component is the only kind that is simply deleted.
- The PWA manifest in `vite.config.ts` carries `background_color` and
  `theme_color` as hex, because a manifest cannot read CSS. They are copies of
  `--surface` and `--accent` and change when those do.
- Every component works in light and dark.
- **Every new or changed component is added to `/kitchen-sink` in the same
  change**, in every variant and state it supports. A component that is not on
  that page is not finished. "Changed" is not decoration: `SectionNav` was
  `SettingsNav` with a different prop shape, and a rule that said "new" did not
  reach it. A component whose signature changed is exactly as unreviewed as one
  that did not exist yesterday.

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
Fortify route or its `/api/auth` twin, and the six My Account calls,
`updateProfile`, `updateBusinessName`, `setMarketingConsent`, `updatePassword`,
`listDevices` and `revokeDevice`, none of which branch at all: the profile and
password routes are stateless twins at the same paths, so one call serves a
session cookie and a bearer token alike. The three writes that answer with a
me payload return it, and the store replaces its copy from the same response
rather than following a write with a read. Every `Me` that arrives, from
`/api/me` or embedded in a token response, goes through one helper that
turns an empty-array `notification_preferences` into an object. Screens
never import it: they call the Pinia store in `src/stores/auth.ts`, and the
store calls this.

### `src/lib/verification.ts`

`useResendVerification()`, which is asking for the verification email again
and coping with all three answers: 202 means one is on its way, 204 means the
address was verified in the meantime so the store is refreshed and whatever
offered the resend takes itself off the screen, and a failure separates a rate
limit from anything else. The home page banner and the email page in My
account both call it. It returns a locale key rather than a string, so the two
callers decide how to show the message and this file names no wording.

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
target. A component test mounts through `mountWithCleanup` from
`src/lib/testMount.ts`, which unmounts after each test on its own, and every
test takes `jsonResponse`, `element`, `typeInto`, `submitForm` and `settle`
from `src/lib/testHelpers.ts` rather than writing its own.

**An assertion that something is absent is paired with an assertion that the
same string is present, in a case where it should be.** A test asserted that
"Send it again" was gone once an address was verified. The locale file says
"Resend the email", so the assertion passed while testing nothing, and would
have passed just as happily against the unverified case it was written to
distinguish. Absences are the dangerous kind: a presence assertion fails when
the wording moves and tells you, and an absence assertion does not, so it
quietly stops being about anything. The pair is what makes it fail.

This is not the rule under the API bullets in "Current state" that a new
assertion is proved by making it fail once, and neither rule finds the other's
bug: that one catches an assertion that can never hold, this one catches an
assertion that always holds.

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
someone mid-form. `/kitchen-sink` has a button that turns `updateAvailable`
on so the bar can be looked at without waiting for a deploy; it reaches that
ref through the same dynamic import inside `if (__WEB_TARGET__)`, so the
mobile bundle is still free of it.

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

The database schema, email-and-password authentication, the mobile
twins of Fortify's registration, password reset and verification routes,
and the four writes the My Account screen needs (profile information,
password, the business name and marketing consent) exist.
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
the `users.marketing_consent_source` check constraint, another gives
`account_settings.deposit_percent` a default of 25 so a bare insert passes
the deposit rule check (decision 90), and the last widens the consent-source
constraint for the `settings` case. Widening a check constraint is always
drop and re-add, generated from the enum, which is why every one of them
is named `<table>_<column>_check`. Framework tables from
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
  **An endpoint that changes the account and answers with the me payload
  changes the instance bound in `CurrentAccount`, not one it fetched
  itself.** `MeResource` reads that bound instance rather than the database,
  so a re-fetched copy leaves the response confirming a rename saying the old
  name, with no error anywhere. `AccountController` is the worked example.
  **A value that feeds a permission decision must not be able to mean two
  things, and the check that collapses it belongs inside the thing returning
  the value rather than repeated at every caller.** Failing closed is right
  for a query and wrong for a decision. This has now cost twice:
  `current_password:web` meant both "wrong password" and "wrong guard", and
  `currentMembership()` as first written meant both "not a member" and "no
  account bound", the second of which answered a request with a 403 about
  ownership. `User::currentMembership()` therefore requires the account
  before it asks the scope anything, so a missing tenant is a loud failure
  naming itself and null means one thing only.
- `App\Services\BookingPricing` (the only place totals are computed),
  `App\Services\InvoiceNumbering` (the only place an invoice gets a number),
  `App\Services\Features` (the only reader of feature toggles),
  `App\Services\PasswordAuthenticator` (the only credential check),
  `App\Services\PasswordChanger` (the only place a password is replaced),
  `App\Services\AccountResolver` (the only place a user's account is chosen)
  and `App\Services\TokenIssuer` (the only place a token is minted).
- `App\Rules\Username` with `config/reserved_usernames.php`. Its
  `reasonFor()` is the single check behind both registration and
  `GET /api/usernames/{username}`.
- `App\Http\Requests\BaseRequest`, which **every form request extends**. A
  form request whose `authorize()` returns false otherwise refuses with
  Laravel's own hardcoded "This action is unauthorized", which is a
  user-facing string written in English in the framework rather than a key in
  `lang/en-GB`. The base turns that into a translated `AuthorizationException`
  and a subclass names its own key by overriding `deniedMessage()`, which
  defaults to `common.not_allowed`. It is a base class rather than a rule in
  this file because a rule people have to remember is a rule that gets
  forgotten on the fourth form request. It is not called `Request`, which
  would sit one namespace from `Illuminate\Http\Request`, nor `FormRequest`,
  which would need an alias in its own file.
- Config that is not the framework's: `config/billing.php` (trial length),
  `config/features.php` (the default feature map), `config/demo.php` (the
  demo password), `config/reserved_usernames.php`. Nothing outside
  `config/` reads `env()`.
- Factories for every model, `SystemDefaultsSeeder` (system message and
  contract templates) and `DemoAccountSeeder` (the "Ellie Marsh Makeup"
  account, which doubles as the App Store review account; owner login
  `ellie@example.com` with the password from `DEMO_PASSWORD`). Its feature map
  starts from `config('features.defaults')`, so the demo is the shape a real
  registration produces, plus three named extras. **Every person and every
  venue in it is invented**, because that account is what gets screenshotted
  and handed to Apple, and a rule about seeded content is checked against the
  strings rather than the columns you expect them in: a real venue survived the
  first sweep inside an `enquiry_message`.
  **It seeds two days the calendar exists to show**, expressed as properties
  rather than dates because it runs whenever it runs: a future Saturday with
  one confirmed booking and three live enquiries, which is the case business
  logic 19.1 names as the reason for the whole screen, and a day in the current
  month with two weddings, seeded as `completed` rather than `confirmed` when
  that date has already passed. `tests/Feature/DemoAccountSeederTest.php`
  asserts both as stage sets rather than exact stages, because a seeder is
  exactly the kind of file edited for one reason that quietly loses a property
  it was carrying for another.
- Pest tests in `api/tests` run against a real Postgres database named
  `klaroly_test`, because the check constraints and partial indexes are part
  of what is tested. Create it once with `createdb klaroly_test`. The
  authentication tests live in `tests/Feature/Auth` and the My Account writes
  in `tests/Feature/Account`. `tests/Pest.php` holds the helpers more than
  one file needs: `actingForAccount`, `createOwner`, `createCollaborator`,
  `sessionRow`, `actingAsWebApp` and `registration`.
- **A test that needs a session against `/api/*` uses `actingAsWebApp`.**
  A JSON test request sends no cookies unless it says it is credentialed, and
  Sanctum only starts a session when the referer is one of its stateful
  domains. Miss either and `request()->hasSession()` is false, `UpdateUserPassword`
  keeps nothing, and a test asserting the other session rows were deleted
  passes because every row was deleted, including the one that should have
  survived. The helper does all of it, and asserts that `FRONTEND_URL` and
  `SANCTUM_STATEFUL_DOMAINS` still name the same host, because they are two
  environment variables and nothing else keeps them in step.
- **A new assertion is proved by making it fail once.** That precondition was
  first written with `expect()->toContain()`, which is variadic, so the
  failure message went in as a second expected value and the assertion was
  that the array contained its own error text. It was caught only because two
  existing tests went red. Written against a passing case it would have
  shipped as an assertion that can never hold, which is the same false green
  it exists to prevent.
- Every date the framework hands back is a `CarbonImmutable`, from
  `Date::use()` in `AppServiceProvider`, so `created_at`, `updated_at` and
  `deleted_at` are not listed in any model's casts. A model casts only the
  columns the framework would not cast on its own.

The app has its authentication screens: sign in, register (two steps in one
form and one route: the email and password, then the business name, full name,
username with its live preview and availability check, and the marketing
consent; a rejection naming a field from the first step takes the form back
there so the message has somewhere to land), forgot password, reset password,
sign out, the verification banner with resend, the verified landing on the
home page, and session restore on load. They are built from the UI kit and
the form kit like every other screen. The router guard awaits
`auth.bootstrap()` once before the first navigation, sends a signed-out
visitor to `/login?redirect=` and follows that redirect after sign-in only
when it is a relative path.

It also has its shell, and every route behind the sign-in exists as a page.
Most of the shell is still furniture rather than features: apart from the
authentication screens and the four My Account pages, **nothing in it calls
the API**, and there is no invented data anywhere. A page that has not been
built says so.

- The routes, all children of the layout route: `/`, `/bookings`,
  `/bookings/:id`, `/enquiries`, `/enquiries/:id`, `/contacts`,
  `/contacts/:id`, `/more`, `/help`, `/account` and its four pages,
  `/settings` and its ten groups, plus `/billing` on the web target. A detail
  route echoes its `:id` and looks nothing up.
- Every page that has not been built is one shared `PlaceholderView.vue`: a
  header and a card saying so. A route names its title with `meta.titleKey`,
  which is also the document title, and where a phone's back link goes with
  `meta.backTo`. When a section is really built it gets a view of its own and
  the routes array points at that instead.
- The pages that are real: `HomeView` (the greeting, the verification banner
  and an empty state), `MoreView` (the phone's overflow list and sign out),
  the settings index, `/settings/travel`, which is the one honest example of
  the form kit doing a section's work and saves nothing because there is no
  settings API yet, all of My account, and `/bookings`.
- **My account is the first section that reads and writes real data.** Its
  index is a list built from `accountGroups`, and its four pages are: your
  details, which is one form over two endpoints and sends only the half that
  changed; your password, which keeps you signed in on this device and says
  the others were not; devices, which lists the tokens from
  `GET /api/auth/tokens` and revokes one at a time; and email and marketing,
  which is read-only apart from a resend and one consent toggle that saves the
  moment it is thrown and goes back if the request fails.
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
announced in words as well as drawn, and, on My account, that the details form
sends only the half that changed and says which of its two requests failed,
that a password mismatch is caught before any request, that the devices list
gets its empty state, its unrevokable current row and a revoke that keeps a
row it could not remove, and that the consent toggle sends on change and goes
back on a failure. Then the three tests that read the source of the app rather
than run it:
`boundary.test.ts`, which stops business logic leaking into components,
`styleRules.test.ts`, which stops a `dark:` variant, an arbitrary value or a
hex colour reaching a component, `lib/bookings.guards.test.ts`, which stops a
component importing the fixtures and stops a day key being built from
`toISOString`, and `router/routeNames.test.ts`, which fails
if any route name written down anywhere is not a route that exists. Renaming
a route is the change that breaks a `router.push` in a screen nobody opened,
and a route name is a string, so nothing else can catch it. Component tests
mount through `src/lib/testMount.ts`, a few lines of `createApp` with the
real router, i18n, pinia and the global kit, because there is no component
testing library and there is not going to be one.

### The bookings endpoints

`GET /api/events` and `GET /api/events/months` are what the bookings screen
reads. `App\Http\Controllers\EventController` serves both.

- **The row says where from `location_type`, not from whether the venue
  columns are null.** `base`, `client` and `venue` per schema 5.9, and null for
  nobody-has-said. The columns cannot tell "not known" from "at her own place",
  because a trial at base has a null venue and a null city, its address being
  in settings, and so does a wedding whose venue is not settled: reading the
  nulls alone said "Venue not given" on every trial. Every branch returns a
  place rather than a phrase, because the line is a run of places separated by
  middots. Business logic 4.1 calls these "at artist", "at client" and "at
  venue"; that disagreement is listed in section 0 of that document.
- **The unit is an event, not a booking**, so four fields on each row are
  per-booking and two events of one booking repeat them. That is deliberate:
  nesting a booking object would make the list sort, group and filter through
  a level of indirection it never needs. The shape is
  `app/src/types/bookings.ts`, and the key list in
  `tests/Feature/Bookings/EventIndexTest.php` is pinned to it so the two
  cannot drift in silence.
- **`from` defaults to today and `to` is unbounded when omitted.** The first
  call the app makes is today with no `to`, which is not laziness: the list
  groups upcoming work into this week, this month, next three months and
  later, and "later" cannot be computed from a subset. The window is the
  fallback for navigating backwards, not the primary mechanism.
- **The cost is capped, because an endpoint whose cost its caller sets is one
  somebody trips over.** `config/bookings.php` holds a span cap of 1830 days,
  applied only when both ends are given, and a row cap of 2000, checked with
  an indexed count before the fetch. Neither can fire on the call the app
  makes by itself.
- **Ordering is total**: `event_date`, then `start_time` with nulls last, then
  `id`. The list renders in this order and must not sort again, and without
  the final `id` two events at the same time could swap between requests.
- **The months summary is presence, not counts**, has no parameters, and is
  **invalidated by writes rather than cached for a session**: any write that
  creates, moves or deletes an event date makes it stale. Its `select
  distinct` goes through the model and never `DB::table('events')`, which
  would bypass the account scope and return every account's months while
  looking perfectly correct in a one-account development database. There is a
  test for exactly that, separate from the windowed endpoint's.
- **`App\Services\WaitingOnResolver` is axis two of the lifecycle** (business
  logic section 6), takes a booking and returns an enum, because the Home
  attention block in 18.1 is the same calculation. Precedence is a list in one
  place, first match wins: not held, balance, deposit, **enquiry cold**,
  price, review, signature, form. Cold sits above price because both describe
  an enquiry at Possible with no quote, so with price first the cold value
  could never be reported at all. Suppression by feature is inside each
  branch, not a filter over the top, so with invoicing off the money checks
  never run rather than running and having their answers discarded.
- **Two of the eight values are unreachable on purpose.** `client_form` and
  `artist_review` both need `intake_forms`, which is schema section 7.4:
  designed, not migrated. The branches exist and return nothing, and a test
  asserts they are unreachable by design rather than by accident.
- **Eager load or this becomes the slowest thing in the app.** A test asserts
  the query count does not grow with the number of events. One eager load
  looks redundant and is not: `booking.lines.booking` is there because
  `booking_lines` has no currency column, so `MoneyCast` resolves a line's
  currency through its booking, and without it that is a query per line.

### The bookings screen

`/bookings` is a month calendar and a list, two views of one set of events on
one screen, per business logic 19.1. It is the first screen built against a
seam rather than against the API: there is no bookings API yet, so
`src/lib/bookings.ts` is its data module, sitting where `src/lib/auth.ts`
sits: below the store, above `api.ts`, and the only other file that turns a
URL into a domain shape. It exports `events({ from, to })` and
`eventMonths()`, both unwrapping the `data` envelope.

**The unit is an event, not a booking.** A booking is one record at a stage
and its dates live in `events`, normally a trial and a `main`, so a row in the
list and a mark on a day are both an event carrying its booking's stage,
client and total. `src/types/bookings.ts` is that view model in **snake_case**,
matching the API and `src/types/auth.ts` before it, and every field in it is a
column in `docs/database-schema.md`: the wedding day is `main` and there is no
`wedding` type, money is `total_minor` beside its `currency`, and
`last_touched_at` is the UTC instant rather than a day count, which would go
stale in an open tab. **Timestamps are parsed, never compared as strings**: the
API sends microseconds, which is Laravel's correct ISO 8601, and the same
moment can be written more than one way.

- **The store holds a list of loaded ranges, not one span.** The first load
  asks from the first of the current month forward, so a normal session makes
  one call and never another. It sends `from` rather than letting the API
  default to today, because the calendar opens on the current month and that
  month starts before today: with the default, a Saturday already worked would
  draw as empty, which is a lie about the artist's own diary rather than a gap
  in a feature. The default stays right for every other caller. Moving to a month outside
  what is held fetches that month with a month either side and merges by event
  id. The list of ranges is not tidiness: the jump sheet advertises every
  month the account has ever worked, and a contiguous backfill from today to
  January 2020 is past the API's span cap, so it would be refused and the month
  the artist asked for would never load.
- **The range guard lives in the store, and it is asked about the month, not
  the window fetched around it.** The scroll sync changes the month as the
  artist scrolls, so `ensureMonthLoaded` is called constantly and must be free
  when there is nothing to do. Testing the padded window instead puts its start
  before today, nothing ever looks loaded, and scrolling forward fires a
  request per month.
- **A window failure never clears the list.** `status` covers the first load
  and `windowStatus` a window, so a month that would not load is a month with
  no marks, which is recoverable, rather than an empty screen, which is not.

- **A mark answers one question: is this day spoken for.** So a stage that no
  longer holds the date carries nothing. `confirmed`, `completed` and `closed`
  are a filled circle, `provisional` is a ring, `possible` and `quoted` are a
  count badge that appears *alongside* either rather than instead of one, and
  `new`, `in_conversation`, `lost` and `cancelled` carry no mark at all.
  `in_conversation` has none because business logic 5.1 puts the soft hold at
  Possible, and cancelled has none because the date is free again. The three
  differ by shape before they differ by colour, because roughly one man in
  twelve cannot separate them by hue. Strength is computed in
  `src/lib/dayMarks.ts` and never stored, per schema section 8.
- **`MonthGrid.vue` has never heard of a booking.** It takes a month, a marks
  map keyed `'YYYY-MM-DD'`, a selected day and a density, and emits a date.
  That is what would let it draw availability or blocked-out days later: a
  caller with a different idea of what a day means builds a different map.
- **The grid is built by walking calendar dates, never by adding 24 hours,**
  and a day key is `format(d, 'yyyy-MM-dd')`, never `toISOString()`, which is
  UTC and would file an evening event under the previous day for the eight
  months the clocks are forward. `src/lib/monthGrid.ts` is the only place
  either happens, and the guard test bans `toISOString` everywhere in the
  feature except the fixtures, where it serialises a UTC instant and is
  correct. **The obvious version of the DST test cannot fail**: both British
  clock changes are on a Sunday, so with Monday-first weeks they are always
  the last day of their week, and a naive build anchored at midnight survives
  the spring forward too. It is the October month grid that breaks, repeating
  the 25th, and that is the assertion carrying the weight.
- A month renders the four, five or six rows it actually needs and is never
  padded to 42 cells. On a 375px phone a row saved is 49px, which is most of
  another booking on screen.
- **The two halves sync in one direction only.** Scrolling the list moves the
  calendar; nothing the calendar does ever scrolls the list, and anything the
  calendar drives holds the sync off for 450ms so the two cannot chase each
  other. The scroll handler changes the month and nothing else, so the list's
  props do not change and Vue leaves it alone: a full re-render would rebuild
  the list and throw away the scroll position the handler is reading from.
  The sync is off in week mode, because a seven-day strip cannot meaningfully
  follow a list spanning three months.
- **The month's height is animated by a watcher, not by the caller.** There
  are five ways to change the month, the arrows, the swipe, a day in the
  padding, the jump sheet and Today, and the first version wrapped only the
  one that went through the tap handler.
- The layout switches on a **container query** at `--container-split`, not a
  media query, so the calendar is a band above the list below it and a column
  beside it above. The container is `<main>`, which is the viewport minus the
  sidebar once the sidebar appears, so at 1024px the screen is stacked with a
  688px container: the calendar therefore carries a maximum width, or it draws
  97px cells and pushes the list off the bottom.
- **The list's group headings rest under the calendar, not behind it.** Both
  are sticky, so on a phone, where the calendar is a band across the top, a
  heading pinned to the same line is invisible. The list wrapper sets
  `--stick-offset` from a measurement, and the test is geometric rather than a
  second copy of the breakpoint: if the calendar's right edge is left of the
  list they are side by side and the offset is zero. Each band is also its own
  list item wrapping its own rows, because a sticky element is bounded by its
  containing block, and in one flat list every heading pins to the top of the
  page at once and only paint order decides which is visible.
- **`MonthJumpSheet.vue` is not `ui/Sheet.vue`** and must not be merged into
  it: Sheet's anchor is a closed set of two fixed sidebar geometries and this
  panel hangs off a button whose position is measured. What they share, the
  focus trap, Escape, the scrim and returning focus to the trigger, is
  `useDialogBehaviour` in `src/lib/dialog.ts`, which both call. It is
  teleported to the body, because `container-type: inline-size` makes an
  element the containing block for its fixed descendants and the scrim would
  otherwise stop at the page's edges.

Not built yet on that screen: the Upcoming, Past and All tabs and the status
filter from 19.2, the clash warning from 5.2, and the booking detail screen.

Not built yet: passkeys and two-factor enforcement (configured, unused),
Sign in with Apple or Google, switching between accounts, collaborator
invitations, account deletion, client login, billing, and persistent token
storage on native (see `src/lib/tokenStorage.ts`).

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
| GET | `/api/contacts` | auth:sanctum, account | No parameters. Every contact, each with its bookings and its computed fields, ordered by what is coming up. `meta` carries `total`, `returned` and `truncated` |
| GET | `/api/enquiries` | auth:sanctum, account | No parameters. Every booking at an enquiry stage, one row per enquiry, ordered by neglect. `meta` carries `total`, `returned` and `truncated` |
| GET | `/api/enquiries/{booking}` | auth:sanctum, account | One enquiry: the list row plus `enquiry_message`, `party_size` and `notes`. 404 for anything the list does not show |
| PATCH | `/api/enquiries/{booking}` | auth:sanctum, account | `stage`, and `lost_reason` when that stage is lost. Answers with the detail shape. 422 for a booking at confirmed or beyond |
| GET | `/api/home` | auth:sanctum, account | No parameters. The home screen's three blocks in one payload: `attention`, `upcoming` and `money`. `meta` carries the resolved feature map, the attention total before the cap, and the account's own day and zone |

### The rules

- **Every authenticated API route sits in the `['auth:sanctum', 'account']`
  group.** `account` is `App\Http\Middleware\BindCurrentAccount`, which
  resolves the user's account through `App\Services\AccountResolver`
  (`last_account_id` if they still belong to it, otherwise their first
  membership by id), binds it as `CurrentAccount` and saves `last_account_id`
  when it changed. A user with no membership gets a 403. Nothing else binds
  the tenant for a request.
- **Route-model binding on a scoped model needs the tenant bound before it
  runs, and `bootstrap/app.php` is what makes that true.**
  `SubstituteBindings` comes from the `api` group and `account` from the route,
  so the natural order is auth, bindings, account: the binding query runs with
  no account bound, `BelongsToAccount`'s scope becomes `where 1 = 0`, and every
  `{booking}` and `{contact}` route answers 404 for rows the caller owns. One
  line fixes it for every route that will ever bind a scoped model:
  `$middleware->prependToPriorityList(SubstituteBindings::class, BindCurrentAccount::class)`.
  **The 404 is the bug; the reason this is written down is the test.** A
  tenancy assertion that another account's row is not found passes just as
  happily when nothing is ever found, so it cannot tell the fix from the bug.
  Every tenancy test on a bound route therefore asserts, in the same test, that
  the caller's own row **is** found. That is decision 2026-09-06.1435, a
  regression test is not a test until it has failed, arriving from a new
  direction.
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
- Packages in use: Fortify, Sanctum, Cashier, Resend, Sentry and the AWS SDK
  behind the `s3` disk. DomPDF and spatie/icalendar-generator are installed
  ahead of the invoice PDF and calendar work and nothing reads them yet.
- The locale directory is `lang/en-GB/`. No user-facing string may be a
  literal in a controller, a Blade template, a mail class or a notification.
  Use translation keys only.
- Key names describe meaning, not content: `booking.deposit_due`, never
  `booking.your_deposit_is_due_soon`.
- Config comes from `.env`. `.env.example` lists every variable a Klaroly
  deployment sets, with a one-line comment; keep it complete when you add one.
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
  variant applies it, and the top bar's create button is the one hand-rolled
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
  beside it. `StatusPill` reads all four families plus a
  neutral and an `accent` tone, the last of which is not a status at all and
  says "this is the thing about this row", which is what Contacts uses for
  Upcoming. My account is the first screen to use two of the status families: `warning` on an unverified email
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
  defines the inset utilities with `@utility` and they are the only place an
  inset is read: `page-top`, `page-under-bar` (clears the top bar as well),
  `page-bottom` (clears the tab bar), `bar-top` (where the top bar's row sits),
  `bar-bottom` (where the tab bar floats), `sheet-bottom`, `above-bar` (a
  sticky row of form actions) and `stick-top` (where a sticky block comes to
  rest). Compose them with Tailwind variants, for example `max-lg:page-bottom`.
  **A sticky block uses `stick-top`, never `top-0`**, which tucks under the
  status bar the moment a native shell asks for an edge-to-edge layout, which
  `viewport-fit=cover` in `index.html` already sets the app up for. It also
  reads `--stick-offset`, for the second sticky block on a screen that has to
  rest under the first rather than behind it; whoever sets that offset owns
  measuring it.
- The focus ring is another `@utility`, `focus-ring`, written as
  `focus-visible:focus-ring` on anything that takes focus and has no edge of
  its own to recolour (a button, a link, a navigation item, a checkbox), and
  as `peer-focus-visible:focus-ring` on the radio card. It reads
  `--border-width-focus` and `--border-focus`, so every ring in the app is one
  rule. A control with a visible edge recolours that edge instead, through
  `edgeClasses` in `src/components/form/field.ts`.
- `chip` and `chip-danger` are two more, and they are the small
  action on something that already exists: 30px painted, taken to the 44px
  minimum by a pseudo-element whose arithmetic reads `--tap-target-min` and the
  chip's own height, so no number in it stops being true when either moves.
  They are a class rather than a component because half their uses are ordinary
  links and half are buttons, and a component wrapping either would be a switch
  on the element type where a class is nothing at all. Focus is deliberately
  not in the utility: a chip carries `focus-visible:focus-ring` in the markup
  like every other ringed control.
- `check` and `radio` are two more, and they are the tick box
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
create action is in the array with a null route name and no tab bar flag: it
is the top bar's accent button on a phone and the sidebar's New button at
`lg`, so the tab bar reads Home, Bookings, Enquiries, Contacts, More.

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
  with the New button at the top and, at the bottom, the account row that
  opens `AccountMenu.vue`. It never links to More.
- `AppTopBar.vue`, below `lg`: the fixed top bar with the screen's title, the
  notifications button and the create button, which is the accent twin of
  `AppButton`'s primary variant. `barGlass.ts` is the material the two bars
  share.
- `AppTabBar.vue`, below `lg`: a bar that floats clear of the bottom edge,
  five destinations and nothing else; the create action used to be a raised
  button in the middle of it and is now in the top bar. The
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

`src/components/ui` is PageHeader, Card, EmptyState, LoadFailed, AppButton,
IconButton, Sheet, AnchoredSheet, Notice, Icon, StatusPill, ListRow, DataTable
and SectionBand. `AppButton` is
the only button component in the app: anything that looks like a button is
that with a different variant or size. A click on it, or on `IconButton`, is
the native event on the root element; neither declares an event of its own.
`Icon` is the only place an icon lives, as a list of SVG paths on a 24 by 24
grid, stroked with `currentColor`, and it exports `iconNames` so the kitchen
sink draws the set from the same list. There is no icon package and there
will not be one.

**`Sheet` and `AnchoredSheet` are two panel components and neither may become
the other.** Both are a bottom sheet below `lg` and a small panel at `lg`, both
teleport, both call `useDialogBehaviour`. The difference is the anchor and it is
the whole reason there are two: `Sheet`'s is a closed set of two fixed sidebar
geometries written as classes, and `AnchoredSheet`'s is a rectangle measured at
runtime with `getBoundingClientRect`, because its trigger's position depends on
how long a month's name is or where a button sits in a column. `AnchoredSheet`
is the only component in the kit that measures anything, and that measurement is
exactly what it exists to own: two callers writing their own
`getBoundingClientRect` is what it was extracted to stop.

Its props are `label` (an already-resolved translation key), `anchorTo`, `align`
and `widthClass`, the last two required with no default because the callers are
split between values and a default would promote one of them to a rule by
accident. **`widthClass` stays a Tailwind width utility rather than a named
set**, and that is settled rather than deferred: five callers want three widths,
and each is an independent constraint rather than a taste. The month jump needs
320 because three 96px month cells plus their gaps come to it, and Adjust shares
that width; the two view
menus need 300 to stay over a 400px list column; the stage sheet needs 352
because its rows carry a second line of explanation. Collapsing them to two
would mean failing one of those to tidy a prop, and naming all three would be
the size scale this component deliberately does not have. **The signal to
revisit is a fifth caller wanting a FOURTH width**, which would mean the panel
is being asked to be more than one thing. **`align` is not a placement variant and must not grow into one**: a
variant is a caller's choice about appearance and it multiplies, whereas this is
a fact about where the trigger sits, in the same category as `anchorTo` itself.
`anchorTo` says which rectangle and `align` says which of its two vertical
edges, and there is no third value it could take. It is deliberately not
inferred from the trigger's position either: choosing the edge that keeps the
panel on screen would silently move an existing caller at some widths, and it
cannot be tested deterministically without stubbing the viewport as well as the
rectangle. **The trigger for revisiting that is a caller that cannot answer the
question, not simply another caller.** Neither panel re-measures on resize or
scroll, so both go stale if the window changes while one is open; that is
carried over from the two components it replaced and is its own change.

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
theme block is what actually runs. The two differ in `--radius-card` and
`--duration-base`, both deliberate, and the app's block has grown tokens the
record never had, so the record is provenance and not a file to paste.

Control heights and the button size ramp have not moved yet: `AppButton` is
still on Tailwind's `h-10` and `h-12`. The type scale is in use for every step
but figure, which waits for the screen that needs it.

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
  them, and the kitchen sink's token page says "Not used yet" beside the
  colour tokens nothing reads. **That note
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
`src/` that touches it. `main.ts` loads it, and `App.vue` loads the
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
  Three enums have no constraint behind them, for two different reasons.
  `WaitingOn` and `EndingSide` are computed and never stored (schema section
  8), so there is no column to guard. `LostReason` does back a column,
  `bookings.lost_reason`, and its constraint is deferred to the schema rewrite
  rather than added as an ALTER migration of its own; the enum holds the line
  at the application boundary until then, and the migration is generated from
  `checkConstraintSql()` like every other one.
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
- `App\Services\BookingPricing` (the only place totals are computed, and the
  only place that decides whether a booking has been priced at all),
  `App\Services\OutstandingBalances` (the only place a contact's debt is
  grouped by currency; it calls `Invoice` rather than re-deriving a balance),
  `App\Services\ContactActivity` (the only place that decides which event a
  booking is shown by; the enquiries endpoint reads its `mainEvent()` too, so
  the name is now half a lie and becomes `BookingActivity` the next time
  somebody is in that file for another reason),
  `App\Services\EnquiryClashes` (the only place that says what else is already
  on a date),
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
  demo password), `config/contacts.php` (the contacts ceiling),
  `config/bookings.php` (the enquiries ceiling, the cold threshold, the events
  caps, the home screen's upcoming count and the intake flag) and
  `config/reserved_usernames.php`. Nothing outside `config/` reads `env()`.
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
  it was carrying for another. **It also seeds the enquiries the enquiries
  screen exists for**, and for the same reason: the account had no enquiry at
  `in_conversation`, none without a date, and no ending on the artist's side,
  so `GET /api/enquiries` could have been built, seeded and screenshotted
  without either of the two cases decision 234 names as the reason it is one
  row per enquiry rather than one per event. Those are asserted as properties
  rather than counts, because a fourteenth enquiry arriving is not a regression
  and a missing stage is.
- Pest tests in `api/tests` run against a real Postgres database named
  `klaroly_test`, because the check constraints and partial indexes are part
  of what is tested. Create it once with `createdb klaroly_test`. The
  authentication tests live in `tests/Feature/Auth` and the My Account writes
  in `tests/Feature/Account`. `tests/Pest.php` holds the helpers more than
  one file needs, from `actingForAccount`, `createOwner` and `actingAsWebApp`
  to `todayFor`, `enquiry`, `issuedInvoice` and `paymentOf`.
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
- **The Postgres session timezone is pinned to UTC in `config/database.php`,
  and it is not a preference.** Eloquent writes a datetime as `Y-m-d H:i:s`
  with no offset, and Postgres reads a naked timestamp into a `timestamptz`
  column using the session's own timezone, which it inherits from the server
  unless told otherwise. On a machine defaulting to `Europe/London` every
  instant the app stored went in an hour early for the eight months the clocks
  are forward, which breaks "audit, signature and financial timestamps are UTC
  without exception" everywhere at once. **Nothing caught it because everything
  wrote and read through the same shift**, so every ordering, every "is this
  overdue" and every relative comparison in the suite still held; it shows only
  when a stored value is compared against an in-memory `now()`.
  `tests/Feature/DatabaseTimezoneTest.php` is that comparison, kept so a
  machine or a managed database with a different server default fails there
  rather than in an audit trail.

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

The screens, and what each reads:

- Home, at `/` and `/attention`: built, on `GET /api/home`.
- Bookings, at `/bookings`: built, on `GET /api/events` and
  `GET /api/events/months`. `/bookings/:id` echoes its id and looks nothing up.
- Enquiries, at `/enquiries` and `/enquiries/:id`: built, on all three
  enquiry routes, and the first screen that writes to a booking.
- Contacts, at `/contacts` and `/contacts/:id`: built, still on
  `src/lib/contactFixtures.ts`. `GET /api/contacts` exists and the screen has
  not been moved onto it.
- My account: its four pages read and write real data through the routes in
  the table above.
- Settings: the index and `/settings/travel` are real, and the latter saves
  nothing because there is no settings API. More is real. Every other page is
  `PlaceholderView.vue`, which says so.

Two rules the screens share. A route names its title with `meta.titleKey`,
which is also the document title, and where a phone's back link goes with
`meta.backTo`. Every route has one `<h1>`, there is a skip link to `<main>`,
and both navigations are real `<nav>` landmarks.

### The feature records

Each screen and endpoint has a record under `docs/` of the decisions it was
built on: the measurements, the decision numbers, and what was tried and
removed. The rules those records established are stated here, and the
reasoning behind each one is in the file it links to. A change to a screen is
a change to its record.

### The home screen

`docs/home-screen.md`. The rules it established:

- Attention and Next up disappear when they are empty. Money never does, and no
  feature toggle removes it: the toggles take the cash half away one figure at
  a time.
- The blocks keep a fixed order and drop out of it. They never rearrange
  themselves.
- `/attention` is a route, not a view flag, and it needs its line in
  `sectionKey` or the tab bar's pill hides with no error anywhere.
- The attention list is cut on the array's order first and grouped after, so
  the preview cannot lose the client group. The band headings count what is
  drawn and "See all N" carries the real total.
- The DOM order is the artist's order, by grid placement and never CSS `order`.
- Every day count is computed at render against `meta.today`, the account's
  day, never the device's.
- Only money that is genuinely late wears a colour.
- Adjust is two settings and must not grow a third. The money period lives on
  the block.

### The enquiries screen

`docs/enquiries-screen.md`. The rules it established:

- The list is a `role="list"` of `role="listitem"`, never a listbox: a control
  inside a row rules the option pattern out, and
  `src/lib/enquiries.guards.test.ts` fails on a listbox, an option or
  `aria-activedescendant` anywhere in the feature.
- Gone quiet is the server's `waiting_on`. The client computes no cold
  threshold of its own.
- Colour on a row means attention, not stage: warning and danger are reserved
  for the staleness figure and the clash line.
- The stage pill is the control, and an ending is two taps.
- A sheet that swaps its own contents calls `refocus()`.
- "No date yet" is a first-class value, and `total_minor` null and nought
  render as different facts.
- Which detail sections to draw is decided by the feature map before the
  stage, in `src/lib/enquirySections.ts`.

### The bookings endpoints

`docs/bookings-endpoints.md`. The rules it established:

- A row says where from `location_type`, never from whether the venue columns
  are null.
- The unit is an event, and a booking's per-booking fields repeat on each of
  its events.
- `from` defaults to today and `to` is unbounded when omitted. The span and row
  caps in `config/bookings.php` never fire on the call the app makes by itself.
- Ordering is total: `event_date`, then `start_time` with nulls last, then
  `id`. The list renders in that order and must not sort again.
- The months summary is presence, goes through the model and never
  `DB::table()`, and is invalidated by writes rather than cached.
- `App\Services\WaitingOnResolver` is the one place the waiting-on axis is
  computed. Its precedence is a list, first match wins, suppression by feature
  is inside each branch, `lost` and `cancelled` wait on nobody, and cold fires
  at every live enquiry stage.
- Eager load what the resolver reads, including `lines.booking` and
  `invoices.payments.booking`, and hold the query count with a literal.

### The contacts endpoint

`docs/contacts-endpoint.md`. The rules it established:

- No parameters, no pagination and no filter, and the ceiling is a flag in
  `meta`, not a 422.
- Ordering is work ahead of you first and soonest first, then history newest
  first, then everybody with neither, ties on id.
- `App\Services\ContactActivity` is the one place that decides which event a
  booking is shown by.
- `outstanding` is an array with one entry per currency, each carrying
  `is_account_currency`, and nothing depends on its order.
  `App\Services\OutstandingBalances` groups and never recalculates a balance.
- The ordering is written in SQL and in PHP, and a test holds the two
  together.

### The enquiries endpoint

`docs/enquiries-endpoint.md`. The rules it established:

- There is no enquiries table and there never will be. The route returns
  bookings at `Booking::ENQUIRY_STAGES` plus `lost`, and the boundary is
  provisional: nothing in the system promotes an enquiry on its own.
- One row per enquiry, never one per event.
- No parameters and no `stage` filter. Staleness is the order, New is pinned
  above it, `lost` sorts last, and the ceiling is a flag in `meta`.
- `waiting_on` comes from `WaitingOnResolver` and is computed nowhere else.
- `clash` describes what is already on the date, uses the calendar's own
  stage buckets from `strengthByStage`, and is one query for every date in the
  payload.
- How an enquiry ended is `lost_reason` with `lost_side`, not a stage.
- Nothing writes `where('account_id', ...)` by hand; the clash query is built
  from `Event::query()`.
- `total_minor` is null when nobody has priced the enquiry,
  `BookingPricing::isPriced()` decides, and the currency is sent regardless.

### The enquiry detail and the stage write

`docs/enquiry-detail-and-stage-write.md`. The rules it established:

- The detail resource composes the list resource and computes nothing of its
  own.
- The write is one route taking a stage, never `/convert` and `/lost`, and it
  is not a general booking update.
- `Booking::LISTED_STAGES` and `Booking::SETTABLE_STAGES` differ by
  `provisional`: after converting, the client holds an object it may PATCH
  back but may not GET.
- A booking at confirmed or beyond is refused with a 422 on `stage`, from the
  request's `after()`, not a 403.
- `lost_reason` is `prohibited_unless`, and both rules are built from the
  enums rather than a second list of values.

### The home endpoint

`docs/home-endpoint.md`. The rules it established:

- One route rather than three: the owed headline is the sum of the
  `client_balance` rows.
- Every live booking is loaded with the resolver's relations, and
  `HomeQueryCountTest` pins the count both as a literal and as flat.
- The attention block is capped after the ordering, and decision 217's
  precedence lives on `App\Enums\WaitingOn`.
- A row's money is the booking's, summed on `App\Support\AttentionRow`, and
  the headline reads the same methods.
- A row sends raw material and never a sentence or a day count; `party` is
  sent rather than parsed.
- The money block is never removed by a toggle, `basis` says what the period
  figure is, and no figure is communicated by an absent key.
- `outstanding.snoozed_minor` is a subset of `overdue_minor`, never a third
  bucket, and `owed_minor` is summed before the cap.
- All four periods come back at once, every period ends today, and under
  `booking_value` a booking counts under its main day.
- Money is filtered to the account currency and `excludes_other_currencies`
  says so.

### The soft hold, its length and its writer

`docs/soft-hold.md`. The rules it established:

- `account_settings.hold_days`, defaulting to 14, is the hold length.
- Every write path that changes a stage calls `App\Services\SoftHold`,
  explicitly, the way it calls `touchActivity()`.
- The rule is a comparison of `App\Enums\HoldClass`: a class going up starts a
  hold, a class of none clears it, anything else leaves it alone.
- The hold never releases itself. Expiry changes what the app says, never the
  data.
- The expiry is stored, not derived, and the seeder computes its holds through
  the same service.

### `last_touched_at`, and the rule that keeps it true

`Booking::touchActivity()` sets the column and saves, the way Laravel's own
`touch()` does, so a caller writes `$booking->fill([...])->touchActivity()` and
one write reaches the database.

**Every write path that touches a booking, or anything belonging to one, calls
it.** That is the rule, and it is a rule rather than a line in one controller
because the stage write is the first of several writers: notes, messages, price
changes and the intake form are all still to come, and each of them is somebody
forgetting.

**A writer that does not call it is a bug in the enquiries list, not in
itself.** `last_touched_at` is what that list is ordered by and what the cold
branch of `WaitingOnResolver` reads, so the damage arrives on a screen the
writer never touches: the top of the list is silently wrong, the Home attention
block agrees with it, and nothing fails. No existing test would catch it either,
because every ordering test sets its timestamps by hand. The one that would is
the pair in `EnquiryUpdateTest`: the column asserted against a named instant,
and the row's position in the list asserted to move with it.

### What the contacts work found in the bookings endpoint

`docs/bookings-endpoint-defects.md`. The rules it established:

- `Invoice::paidMinor()` reads the loaded relation when there is one.
- `invoices.payments.booking` is eager loaded beside `lines.booking`, for the
  same reason: neither table has a currency column.
- `isOverdue()` takes the day to judge against, and it is the account's.

### The bookings screen

`docs/bookings-screen.md`. The rules it established:

- The unit is an event. `src/types/bookings.ts` is snake_case and every field
  in it is a schema column, and timestamps are parsed, never compared as
  strings.
- The store holds a list of loaded ranges, the first load asks from the first
  of the current month, the range guard lives in the store, and a window
  failure never clears the list.
- A mark answers whether a day is spoken for. Strength is computed in
  `src/lib/dayMarks.ts` and never stored, and `MonthGrid.vue` knows nothing of
  bookings.
- The grid walks calendar dates, and a day key is `format(d, 'yyyy-MM-dd')`,
  never `toISOString()`. `src/lib/monthGrid.ts` is the only place either
  happens.
- The two halves sync in one direction, and the month's height is animated by
  a watcher.
- The layout is a container query at `--container-split`, and the group
  headings rest under the calendar through `--stick-offset`.

### The contacts screen

`docs/contacts-screen.md`. The rules it established:

- A contact is the person who books and pays, and that is all. `last_name` is
  nullable and the whole feature has to mean it.
- `outstanding` is an array of amounts per currency, never a figure and a flag.
- One payload, and every sort, group and filter happens in the browser. The
  filter box is a filter, with no request behind it.
- The row's second line is always the nearest booking, and there is one pill
  at most, in precedence order.
- The view settings live on the device in one localStorage key, with every
  read and write wrapped, and each field checked rather than cast.
- The list is a listbox and the filter field is its combobox; `aria-selected`
  is the keyboard cursor and `aria-current` is whose card is open.
- There is one document scroll container.
- The refusal and the confirm are two different controls: a polite live region
  and a real dialog.
- The chip is a class in `app.css`, not a component.

Not built yet: passkeys and two-factor enforcement (configured, unused),
Sign in with Apple or Google, switching between accounts, collaborator
invitations, account deletion, client login, billing, and persistent token
storage on native (see `src/lib/tokenStorage.ts`).

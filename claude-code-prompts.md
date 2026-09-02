# Klaroly: Claude Code Prompts

**Date:** 1 September 2026. Prompt 3 rewritten and Prompts 4, 5 and 6 written 2 September 2026
**Where this file lives:** `claude-code-prompts.md` at the root of the `klaroly` repository is
the working copy, alongside `docs/database-schema.md`. The copy in the Dropbox project
folder mirrors it.
**How to use:** open a terminal at the repository root, run `claude`, paste one prompt.
Do not paste two at once. Check the output of each before moving on.

House rules that apply to every prompt below, so they are stated once here and
referenced rather than repeated:

> **House rules.** Two-space indentation everywhere. No semicolons at the end of
> JavaScript or TypeScript lines. British English in all user-facing strings and
> in comments. No emoji anywhere in the codebase. Prefer plain, obvious code over
> clever code, because this project has a handover goal.
>
> **npm, not pnpm or yarn.** Settled, decision 59. Do not suggest otherwise. Add
> a `packageManager` field to `package.json` pinning the installed npm version, so
> tooling and agents never have to infer it from a lockfile.
>
> **Installs are fine here.** Claude Code runs natively on macOS, so run
> `composer install` and `npm install` normally. (The no-install rule elsewhere in
> this project applies only to Cowork, whose shell is a Linux VM and would write
> Linux binaries into `node_modules`. It does not apply to you.)
>
> **Do not commit and do not push.** Leave every change unstaged. I review the
> changed files in my Git browser, then commit and push myself. When a prompt
> ends, list the files that changed and stop.
>
> **Do not name any script `deploy`.** Use `ship`. It reads the same and avoids a
> collision with a reserved command if the package manager ever changes.
>
> **Do not commit or push.** From Prompt 4 on, every change is left unstaged so Jack
> can review each file in his Git browser before committing himself (decision 82).
> A prompt ends with "tell me it is done", never with a commit.
>
> **Local environment already in place:** Laravel Herd with PHP 8.4, and
> Postgres 18 via Postgres.app on `127.0.0.1:5432`. Do not install PHP, a web
> server or a database, and do not add Docker or Laravel Sail.

---

## Prompt 1: the marketing site

**When:** evening 2
**Where:** `~/Code/klaroly/klaroly-web` (already built, 1 September)
**Before you start:** have `marketing-copy.md` open, and have `gh` and `wrangler`
installed (`brew install gh`, `npm i -g wrangler`), both logged in.

```
Build the Klaroly marketing site.

HOUSE RULES
Two-space indentation. No semicolons at the end of JavaScript or TypeScript lines.
British English in all user-facing strings and comments. No emoji anywhere.

STACK
- Astro, latest version, static output only. No SSR adapter, no server islands.
- Tailwind CSS via the official Astro integration.
- @astrojs/sitemap for sitemap generation.
- Zero client-side JavaScript unless a page genuinely needs it. The contact form
  is the only candidate and it should work without JS if possible.
- Deployed to Cloudflare Workers using static assets, not Cloudflare Pages.

PAGES
Five pages, content taken verbatim from the file I will paste after this prompt:
  /            home
  /features    features
  /pricing     pricing
  /about       about
  /contact     contact and early access

Each page needs its own <title> and meta description, both given in that file.
Set site: 'https://www.klaroly.com' in astro.config so the sitemap has absolute URLs.

STRUCTURE
- A single BaseLayout.astro holding the html shell, meta tags, header and footer.
- A Header component with the Klaroly wordmark and links to the five pages, with a
  simple CSS-only mobile menu (a checkbox toggle, no JavaScript).
- A Footer with the company line, the registered office placeholder, and links to
  privacy and terms, both of which can be stub pages for now with a "coming shortly"
  line and a noindex meta tag.
- Content lives in the .astro pages for now. Do not set up a content collection yet.

DESIGN
Deliberately plain and fast, not a design exercise. I will redesign this later.
- A neutral palette: near-black text on off-white, one accent colour, nothing else.
- System font stack. No web fonts, no font loading at all.
- Max content width around 65 characters for body text.
- Generous vertical spacing. Clear heading hierarchy: exactly one h1 per page.
- Fully responsive from 320px up, using Tailwind's default breakpoints.
- Respect prefers-color-scheme with a dark variant, using Tailwind's dark: prefix.
- No images. Not one. Where the copy suggests one, leave the space.

SEO
- One h1 per page, matching the page's main heading.
- Semantic HTML: header, nav, main, article, footer.
- Open Graph and Twitter card meta tags on every page, taking title and description
  from the page's own front matter.
- JSON-LD on the home page: an Organization schema for Sunday South Ltd, and a
  SoftwareApplication schema for Klaroly with applicationCategory
  'BusinessApplication'. Leave price out of the schema entirely, because pricing is
  not settled.
- robots.txt allowing everything and pointing at the sitemap.
- Canonical link tag on every page.
- The privacy and terms stubs get noindex.

CONTACT FORM
Name, email, and an optional number field for weddings per year. Post it to a
placeholder endpoint constant at the top of the file called FORM_ENDPOINT, set to
an empty string with a comment saying it needs a form handler. Do not integrate a
third-party form service, and do not build a Cloudflare Worker function for it.
I will decide on the handler separately.

CLOUDFLARE
Create wrangler.jsonc for an assets-only Worker:
  name: klaroly-web
  compatibility_date: today's date
  assets: directory ./dist, not_found_handling "404-page"
Add a 404.astro page so that handling has something to serve.
Do not add a Worker script entry point. This site is static assets only.

SCRIPTS
package.json scripts: dev, build, preview, and deploy which runs
"astro build && wrangler deploy".

REPO
Initialise git, write a .gitignore covering node_modules, dist, .astro and
.wrangler, and make one initial commit. Then create a private GitHub repository
called klaroly-web using the gh CLI and push main to it.

FINALLY
Run the build, tell me the output size, and give me the exact commands to deploy
and to attach the custom domains. Do not deploy it yourself.
```

Then paste the contents of `marketing-copy.md` as your next message.

**Check before you move on:** `npm run build` succeeds, `npm run preview` serves all
five pages, view-source shows the body copy in the HTML, and Lighthouse SEO is 100.

---

## Prompt 2: scaffold the monorepo

**When:** now
**Where:** open Claude Code in `~/Code/klaroly/klaroly` (the empty cloned repo)
**Before you start:** Herd running with PHP 8.4, Postgres.app running, a `klaroly`
database created, Node 24.

Claude Code has no access to this Dropbox folder, so the prompt below is written to
be entirely self-contained. Paste all of it.

```
Scaffold the Klaroly monorepo in this directory. It is an empty git repository with
its remote already set.

This is STRUCTURE ONLY. Do not write business logic, do not write migrations beyond
what the installers generate, do not run `php artisan migrate`, and do not build any
screen beyond a login placeholder. The database schema is a separate exercise and
changes the stock users migration, so leave it alone.

## House rules

- Two-space indentation everywhere.
- No semicolons at the end of JavaScript or TypeScript lines.
- British English in every user-facing string and in comments.
- No emoji anywhere in the codebase.
- Plain, obvious code over clever code. This project has a handover goal: a
  competent developer who has never seen it should be able to read it.
- **npm.** Not pnpm, not yarn. Add a `packageManager` field to each package.json
  pinning the installed npm version.
- Name no script `deploy`. Use `ship`.

## Environment already in place. Do not install these.

- Laravel Herd, PHP 8.4
- Postgres 17 via Postgres.app on 127.0.0.1:5432, database `klaroly`, username
  `jackbarham`, no password
- Node 24, npm

Do not add Docker, Laravel Sail, Redis, or a local mail server.

## Shape

```
klaroly/
  api/          Laravel 13   -> Laravel Cloud, api.klaroly.com and *.klaroly.com
  app/          Vue 3 + Vite -> Cloudflare Workers, app.klaroly.com
  README.md
  CLAUDE.md
  .gitignore
```

The marketing site is a separate repository and is not your concern.

## api/

Install Laravel 13 configured for Postgres, then:

- Packages: laravel/fortify, laravel/sanctum, laravel/cashier,
  resend/resend-laravel, barryvdh/laravel-dompdf, spatie/icalendar-generator,
  sentry/sentry-laravel
- Pest for testing, not PHPUnit. Laravel Pint with the default preset
- Do NOT install Horizon. Laravel Cloud managed queues do not support it
- `lang/en-GB/` as the locale directory. No user-facing string may be a literal in
  a controller, a Blade template, a mail class or a notification. Keys only, and
  key names describe meaning rather than content: `booking.deposit_due`, never
  `booking.your_deposit_is_due_soon`
- `.env` pointing at the local Postgres above, and a `.env.example` listing every
  variable the app will need with a one-line comment each. Include SESSION_DOMAIN,
  SANCTUM_STATEFUL_DOMAINS, RESEND_KEY, SENTRY_LARAVEL_DSN, APP_TIMEZONE=UTC,
  CACHE_STORE=database, QUEUE_CONNECTION=database
- CORS allowing exactly three origins, read from config and never hardcoded:
  https://app.klaroly.com, capacitor://localhost, http://localhost:5173
- Session cookie: SESSION_DOMAIN=localhost for local, `.klaroly.com` in production.
  SameSite=Lax, secure in production only
- Confirm `pdo_pgsql` is loaded and tell me if it is not

## app/

- Vue 3 with `<script setup>`, TypeScript, built by Vite
- **Single-file component block order is `<template>`, then `<script setup>`, then
  `<style>` if one is needed. Every component, without exception.** This is the
  opposite of the Vue tooling default, so if ESLint is added set `vue/block-order`
  to `['template', 'script', 'style']` or it will flag every file in the project
- Vue Router with an EXPLICIT routes array in `src/router/index.ts`. Not file-based
  routing. Do not install unplugin-vue-router
- Pinia for state
- **Tailwind CSS 4, via the `@tailwindcss/vite` plugin.** CSS-first configuration:
  `@import "tailwindcss"` and an `@theme` block in one global stylesheet. Do NOT
  create a `tailwind.config.js`; that is the version 3 approach. This matches the
  marketing site, so the two share one way of defining tokens
- Define the palette, font stack and spacing scale as `@theme` custom properties
  from the start, even if they are placeholders, so nothing hardcodes a hex value
- No UI component framework of any kind. Not Ionic, not any Tailwind component
  library
- vue-i18n with a single `src/locales/en-GB.json`. Every user-facing string is a key
  from the first commit, same naming rule as the API
- dexie installed but unused for now
- vite-plugin-pwa, enabled only on the web build target
- Two build targets switched on `VITE_TARGET`, which is `web` or `mobile`:
    web     service worker on, billing routes included
    mobile  service worker off, billing routes excluded
  Excluded routes must use a dynamic import inside a statically false branch, so the
  chunk is dropped from the bundle rather than merely hidden. A static top-level
  import would keep the code in the mobile binary, which is the thing being avoided
- `src/lib/platform.ts` as the single place any native-versus-web branch may live.
  Export isNative, isIOS, isAndroid, isWeb. Nothing else in the codebase checks the
  platform directly
- `src/lib/api.ts` wrapping fetch. The base URL comes from
  `import.meta.env.VITE_API_URL` and is NEVER derived from window.location. On web
  it sends credentials; on native it sends a bearer token. Callers never know which
- A placeholder login route and an empty dashboard route behind a router guard, so
  the shape is visible. No real auth calls yet
- `wrangler.jsonc` for an assets-only Worker: name `klaroly-app`, assets directory
  `./dist`, `not_found_handling` set to `single-page-application`
- Do NOT run `cap add ios` or `cap add android`. Capacitor comes much later, once
  there is a login screen and one working list view

## CLAUDE.md

Write a CLAUDE.md at the repository root so future sessions do not need to be told
any of this again. Include the house rules above, the two-target build explanation,
the platform.ts and api.ts rules, the locale-keys rule, and these constraints:

- The account is the tenant. Every customer-data table will carry `account_id`,
  including tables that also carry `booking_id`. A user is never a tenant
- All money is stored as a bigint integer in the currency's ISO 4217 minor unit, with a `_minor` suffix. Never float, never
  decimal
- Event dates and times are stored as local wall clock plus an IANA timezone, never
  as UTC. Scheduled reminders are the opposite: computed to UTC when scheduled and
  recomputed if the event moves. Audit, signature and financial timestamps are UTC
  without exception
- Enum-like columns are varchar with a check constraint, never a Postgres enum type
- No enum anywhere lists makeup services. The rate card is rows
- Never derive the API base URL from window.location
- Bearer tokens on mobile, session cookie on web, from the start
- Vue SFC block order is template, then script, then style. Always
- Tailwind 4 with CSS-first `@theme` configuration. No `tailwind.config.js`, and no
  hardcoded colours or spacing outside the theme block

## README.md

Get a competent developer who has never seen this project from clone to running:
prerequisites with versions, api setup, app setup, how to run both together, and
where environment variables come from. Assume they know Laravel and know nothing
about Klaroly.

## .gitignore

One at the repository root covering both halves. Include `.claude/`, `.env`,
`/vendor`, `/node_modules`, `/dist`, `/public/build`, `.DS_Store`, and Laravel's
usual entries.

## Finally

1. Run the installs. You are on macOS natively, so `composer install` and
   `npm install` are fine for you to run.
2. Verify both halves boot: the API responds, and the app dev server serves the
   login placeholder.
3. Stage everything and make ONE commit. Do NOT push. I will push myself.
4. Then tell me: anything in this specification you think is wrong or will cause a
   problem later, and the exact settings I should enter when creating the Laravel
   Cloud application, flagging which of them cannot be changed afterwards.
```

**Check before you move on:** `php artisan serve` in `api/` responds, `npm run dev`
in `app/` serves the login placeholder, `CLAUDE.md` exists, and nothing has been
migrated. Then push.

---

## Prompt 3: the schema

**When:** now, 2 September 2026
**Where:** open Claude Code at `~/Code/klaroly/klaroly` (the repo root). It works in `api/`.
**Before you start:** `docs/database-schema.md` is in the repo and is the specification.
Decision 76 marked it draft for sign-off and decision 77 renamed `_pence` to `_minor`;
both are applied. This prompt deliberately does not restate the tables, so the two
cannot drift. Auth is the next prompt, not this one.

```
Write the first database migration set for Klaroly: migrations, enums, models, casts,
factories, a seeder and Pest tests. Work in api/. Read CLAUDE.md and
docs/database-schema.md in full before writing anything. database-schema.md is the
specification. Where this prompt and that document disagree, the document wins, and
you tell me where they disagreed at the end.

FIRST, BEFORE ANY CODE
Having read both files, list the 22 tables from section 5 of database-schema.md in
dependency order, each with its column count and its check constraints, and list
the section 7 tables you will not create. Then stop and wait for me to confirm the
list. Do not write a migration until I say go.

HOUSE RULES
Two-space indentation, except PHP at four spaces because Pint's default preset
enforces PSR-12. British English in comments. No emoji. Plain code over clever code.

SCOPE
- Create every table in section 5 of database-schema.md, 5.1 to 5.22. Create nothing
  from section 7; those tables are designed and deliberately not migrated.
- Already in place, keep it: Sanctum's personal_access_tokens migration, Fortify's
  add_two_factor_columns_to_users migration, and the passkeys migration. Do not
  duplicate any of those columns.
- Cashier's migrations are not published and you do not publish them. Add the four
  Cashier columns (stripe_id, pm_type, pm_last_four, trial_ends_at) to accounts as
  plain columns per 5.1. The subscriptions tables arrive with the billing prompt.
- Modify the stock users migration in place, 0001_01_01_000000_create_users_table.php,
  to match 5.3: add uuid, make password nullable, add notification_preferences,
  marketing_consent_at, marketing_consent_source, last_account_id (nullable, foreign
  key added in the final migration because accounts is created after users), soft
  deletes, timestampsTz. Replace the plain unique on email with a unique index on
  lower(email). Leave password_reset_tokens and sessions as they are.
- No authentication routes, controllers, Fortify configuration or Sanctum
  configuration. Nothing in routes/. That is the next prompt.

MIGRATIONS
- One migration per table, in dependency order, timestamped after the existing
  migrations. Then one final migration for foreign keys that cannot be declared
  inline: users.last_account_id to accounts, bookings.source_booking_id to bookings,
  agreements.superseded_by_id to agreements.
- Column types exactly as the document: timestampsTz() for created_at and
  updated_at, timestampTz() for every other instant, date and time for wall clock,
  jsonb() never json(), char(2) and char(3) for country and currency, inet for
  signed_ip, decimal(9, 6) for coordinates, bigInteger for every _minor column.
- Every check constraint is a DB::statement in the same migration, named
  <table>_<column>_check, with its value list generated from the matching PHP enum so
  the two cannot drift. Every partial unique index, and the unique nulls not
  distinct constraints on message_templates and contract_templates, are also
  DB::statement. Postgres 17, so nulls not distinct is available.
- Foreign key actions per section 2 of the document: account_id cascades,
  booking_id cascades to its children, service_id sets null, contact_id on bookings
  restricts.
- A comment at the top of the events migration explaining that events are local
  wall clock plus an IANA zone while everything that fires is UTC, and that the two
  are different on purpose.

ENUMS, in App\Enums, string-backed, snake_case values
BookingStage, BookingSource, EventType, LocationType, ServiceKind, ServiceAppliesTo,
LineKind, QuoteStatus, QuoteSentVia, InvoiceStatus, PaymentMethod, AccountRole,
IdentityProvider, TemplateKey, TemplateMode, DepositType, TravelCharging,
AgreementStatus, SignedMethod, EntitlementSource, EntitlementStatus,
BookingContactRole, PhotoConsent, FeatureKey. One small trait giving values() and
checkConstraintSql(string $table, string $column) so migrations build the
constraint from the enum. No enum anywhere lists makeup services; the rate card is
rows in the services table.

MONEY
App\Support\Money: an immutable value object holding an int of minor units and an
ISO 4217 currency code, with add, subtract, multiply by int, percentage, compare,
isZero, isNegative, and format(string $locale) via NumberFormatter. Never a float
anywhere in the class. App\Casts\MoneyCast wraps every _minor column, taking the
currency from the model's own currency column or, where the model has none, from
its booking. Formatting uses currency plus locale, never a hard-coded symbol.

MODELS
- One per table. Relationships in both directions. Casts: enums, jsonb columns to
  array, timestamps to immutable Carbon, access_pin encrypted, every _minor column
  through MoneyCast.
- App\Models\Concerns\BelongsToAccount on every model except User, Account,
  Identity and UsernameHistory: applies a global scope on account_id and fills
  account_id on creating from App\Support\CurrentAccount, a container singleton
  with set(Account), get(), clear() and id(). Creating a scoped model with no
  current account throws. Authentication binds the current account in the next
  prompt; tests set it explicitly.
- Account uses Cashier's Billable trait. Nothing else Cashier-related yet.
- Usernames: normalised to lowercase on set. App\Rules\Username validates the regex
  from decision 55 (^[a-z][a-z0-9]{2,62}$), rejects anything in
  config/reserved_usernames.php, and rejects anything in username_history. Create
  config/reserved_usernames.php with every DNS label and route that must never be
  an artist hostname: app, api, www, mail, smtp, mx, imap, pop, ftp, cdn, static,
  assets, admin, support, help, billing, pay, payments, login, signin, signup,
  register, account, accounts, status, blog, docs, dev, staging, test, demo, mobile,
  m, ns1, ns2, autodiscover, autoconfig, auth, my, portal, client, clients, book,
  booking, bookings, pricing, about, terms, privacy, contact, security, legal,
  klaroly, and anything else you would add. Claiming a username writes a
  username_history row; changing one sets released_at on the old row and writes
  a new one.
- Services, small and single-purpose:
  App\Services\BookingPricing: subtotal, discount, total and deposit for a booking
    as Money, honouring pricing_mode, fixed_price_minor, discount_type and value,
    and the deposit overrides against the account_settings rule. This is the one
    place totals are computed. Nothing is stored.
  App\Services\InvoiceNumbering: issue(Invoice) inside a transaction that locks the
    account_settings row with lockForUpdate, assigns sequence and number
    (prefix, hyphen, four-digit zero-padded sequence), sets status to issued and
    issued_on, snapshots lines and payment_instructions, and increments
    next_invoice_number. Drafts never have a number.
  App\Services\Features: enabled(Account $account, FeatureKey $key, ?Booking
    $booking = null): bool, reading account_settings.features then
    bookings.feature_overrides, with the entitlement check as a method that returns
    true and carries a TODO for the billing prompt. Nothing else reads those columns.
- Derived values from section 8 of the document as model methods, never stored:
  Invoice paidMinor(), outstandingMinor(), depositCovered(), isPaid(),
  isOverdue(); Booking agreementInForce(), isEnquiry(), isBooking(); Event helpers
  for startsAt() as a Carbon in the event's own zone. No paid boolean anywhere.

FACTORIES
One per model, with states for the interesting cases (enquiry stages, fixed price,
issued invoice, signed agreement, refund payment). British names, real UK towns and
postcodes, plausible prices in minor units.

SEEDER
DatabaseSeeder calls two seeders and is idempotent under migrate:fresh --seed.

1. SystemDefaultsSeeder. System rows with account_id null:
   - message_templates for these keys, locale en-GB, vertical wedding_makeup:
     enquiry_acknowledgement, quote, booking_confirmed, invoice_deposit_request,
     main_event_reminder, thank_you. Write them as a person would write them: warm,
     brief, British English, no emoji, merge fields in double braces such as
     {{contact_first_name}}, {{business_name}}, {{main_event_date}},
     {{main_event_day}}, {{start_time}}, {{address}}, {{total}}, {{deposit}},
     {{balance}}, {{balance_due_on}}, {{payment_instructions}}, {{sign_off}}.
     main_event_reminder carries three location variants in the variants column:
     base, client, venue. mode is copy, enabled true.
   - One contract_templates row: market GB, vertical wedding_makeup, version 1,
     effective_from today. The body is plain text with merge fields and a
     cancellation clause section, and its first line reads PLACEHOLDER, NOT LAWYER
     REVIEWED, because the real wording waits on decision 23.

2. DemoAccountSeeder. One realistic account that doubles as the App Store review
   account, so the data must be plausible rather than lorem ipsum. Fictional names
   only.
   - Account "Ellie Marsh Makeup", username elliemarsh, GB, en-GB, GBP,
     Europe/London, trial_ends_at thirty days out. account_settings with every
     feature key true, deposit 25 per cent, deposit due 7 days, balance due 28 days
     before, payment_instructions with fictional bank details, invoice prefix INV,
     base_postcode in Bristol, travel per_mile at 45.
   - Owner user Ellie Marsh, email ellie@example.com, password from
     env DEMO_PASSWORD defaulting to password, email verified, uuid set.
   - The wedding_makeup rate card from section 5.12 of the document, with sensible
     Bristol prices.
   - Eight contacts with names, emails, phones and postcodes across the South West.
   - Fourteen bookings across the next twelve months and the last three:
     three enquiries (new, possible, quoted), one of them source captured_at_event
     with source_booking_id pointing at a completed booking; two provisional, one
     with hold_expires_at in the past; five confirmed spread across the year, one
     in fixed price mode, one with a discount; two completed with an outstanding
     balance so the "owed to you" figure is non-zero; one closed and fully paid;
     one lost with a reason. Every booking has last_touched_at, a currency, and
     between one and three notes. Each has a main event at a real, named South
     West wedding venue with its real postcode and a plausible call time, and most
     have a trial event at the artist's base a few weeks earlier. Party members
     with services. Lines built from the rate card.
   - Two quotes on the quoted enquiry and one on a confirmed booking, status sent
     and accepted.
   - Invoices issued through InvoiceNumbering for every provisional, confirmed,
     completed and closed booking, so the numbers are sequential with no gaps, in
     these states: deposit only paid, fully paid, balance overdue, one with a
     refund as a negative payment with a note, one snoozed.
   - Agreements: version 1 signed manual on every confirmed booking, and on one of
     them a version 2 in draft that supersedes nothing yet. rendered_body from the
     system template, rendered_sha256 computed.
   - A closed booking and the lost booking have no open invoice.

TESTS, Pest 5, against the local Postgres database (not SQLite; the check
constraints and partial indexes are the point)
- Tenancy: two accounts each with bookings; with CurrentAccount set to A, B's
  bookings are invisible through every scoped model, and a booking created without
  an explicit account_id lands in A. Creating with no current account throws.
- The stage check constraint rejects an invalid value with a QueryException.
- Only one main event per booking; a second is rejected.
- users email is unique case-insensitively.
- InvoiceNumbering: three drafts issued in sequence produce INV-0001, INV-0002,
  INV-0003 with no gaps, and a draft never has a number.
- Invoice derivation: deposit covered, outstanding, paid, and a negative payment
  reduces paid.
- BookingPricing: itemised, fixed price, amount discount, percent discount, deposit
  override.
- MoneyCast: reads and writes an int, exposes Money, refuses floats.
- Username rule: rejects uppercase, hyphens, underscores, a leading digit, a
  reserved word, and a released name from username_history.

FINALLY
1. composer lint.
2. php artisan migrate:fresh --seed against the local database.
3. Run the tests.
4. Show the table list and a row count per table.
5. Update the "Current state" section of the repository CLAUDE.md: the schema
   exists, list the tables, say that docs/database-schema.md is the specification
   and that section 7 tables are designed but not migrated.
6. Commit as "Add the initial database schema" and push.
7. Then tell me every place the specification was ambiguous, wrong, or would cause
   a problem later, and what you did about each. That answer is the most useful
   thing you will produce in this prompt.
```

**Check before you move on:** the table list it shows you first matches section 5 of the
schema document (say "go" only when it does), the tenancy test passes, the row counts
look like a real business, and read step 7 carefully. Anything it flags gets folded back into
`docs/database-schema.md` and the decision log before Prompt 4.

---

## Prompt 4: authentication, API only

**When:** now, 2 September 2026, once the Prompt 3 commit is pushed and the tree is clean.
**Where:** open Claude Code at `~/Code/klaroly/klaroly`. It works in `api/`.
**Before you start:** decisions 78 to 84 in `decision-log.md` are what this prompt is
written from; the schema document's section 13 records what Prompt 3 found. The Vue
screens are Prompt 5, deliberately, so that this set of changed files is reviewable on
its own and the screens are written against endpoints that exist.

Two things the repository already has that this prompt must not redo: Fortify is
installed with its two-factor columns and passkeys table migrated and its stock
actions in `app/Actions/Fortify`, and Sanctum's `statefulApi()` is already on in
`bootstrap/app.php`.

```
Build authentication for the Klaroly API. Work in api/. Read CLAUDE.md,
docs/database-schema.md sections 2, 5.1 to 5.6, 10 and 13, and the existing code in
app/Support/CurrentAccount.php, app/Models/Concerns/BelongsToAccount.php,
app/Models/User.php, app/Models/Account.php, app/Providers/FortifyServiceProvider.php,
app/Actions/Fortify/, config/fortify.php and config/sanctum.php before writing
anything. Where this prompt and the schema document disagree, the document wins, and
you tell me where they disagreed at the end.

FIRST, BEFORE ANY CODE
Having read all of that, list every route this prompt will register (method, path,
middleware, and whether Fortify or you provides it), every file you will create or
change, and every new config key and environment variable. Then stop and wait for me
to confirm. Do not write code until I say go.

HOUSE RULES
Two-space indentation, except PHP at four spaces because Pint's default preset
enforces PSR-12. British English in comments and in every translation string. No
emoji. Plain code over clever code. No user-facing string may be a literal; keys in
lang/en-GB/ only, named for meaning rather than content.

DO NOT COMMIT. Do not stage, commit or push anything. Leave every change unstaged so
I can review each file in my Git browser. Tell me when the work is done and I will
commit.

SCOPE
The API side of email-and-password authentication for artists, serving both the web
app (session cookie) and the mobile app (bearer token), plus the tenancy binding
that makes the scoped models return rows. Nothing in app/. No Blade views; every
response is JSON. Not in this prompt, and the columns and tables already exist so
nothing is blocked: passkeys, two-factor enforcement, Sign in with Apple or Google,
switching between accounts, collaborator invitations, account deletion, client
login.

FORTIFY
- Features: registration, resetPasswords, emailVerification, updateProfileInformation,
  updatePasswords. Leave the twoFactorAuthentication and passkeys entries exactly as
  they are; they are configured, unused and not exposed. views stays false.
- Every Fortify response must be JSON. Set config fortify.home to the FRONTEND_URL
  so the two places Fortify redirects a browser (after email verification, and
  intended redirects) land on the web app rather than the API. In bootstrap/app.php
  set Authenticate::redirectUsing to FRONTEND_URL plus /login, so a browser that
  hits a protected API route while logged out is sent to the web app's login page
  rather than to a route that does not exist.
- Email normalisation, decision 84. One middleware, App\Http\Middleware\NormaliseEmail,
  that lowercases and trims the email input on the request. Apply it to the Fortify
  route group through config fortify.middleware (web plus this middleware) and to
  the mobile token endpoint below. Do the same inside CreateNewUser and
  UpdateUserProfileInformation before validation, so the actions are safe when
  called from somewhere other than an HTTP request. The lower(email) index is the
  backstop, not the mechanism.
- Fortify::authenticateUsing: look the user up by lowercased email, check the
  password with Hash::check, and return null otherwise. Never reveal which of the
  two was wrong.
- Uniform forgot-password response, technical proposal section 5 gap 1. Bind
  Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse to a class that
  returns exactly the same JSON body and status as the successful response, so an
  unknown address is indistinguishable from a known one. Add a test.
- Password reset emails link to the web app, not the API:
  ResetPassword::createUrlUsing pointing at FRONTEND_URL/reset-password with the
  token and email as query parameters. The reset itself is POST /reset-password on
  the API, as Fortify provides.
- Email verification: the User model implements MustVerifyEmail. The verification
  link is Fortify's signed API route and, once verified, the browser is redirected
  to fortify.home. No route in this prompt uses the verified middleware; decision 83
  says sent, not enforced. Add a comment in routes/api.php saying where the alias
  goes when enforcement arrives.
- Password rules, gap 5. In AppServiceProvider set Password::defaults to a minimum
  of ten characters plus uncompromised(), except in the testing environment where
  uncompromised() is left off because the suite has no network. PasswordValidationRules
  uses Password::defaults() and nothing else.
- Revoke on password change, gap 3. In UpdateUserPassword and ResetUserPassword,
  after the new password is saved: delete every personal access token the user
  has, and delete every row in the sessions table for that user other than the
  current session (on reset, every row, since there is no current session). Add a
  test for each.

REGISTRATION, decision 84
Replace the stock CreateNewUser. The request carries:
  business_name   required, max 120, becomes accounts.name
  name            required, max 120, the person
  email           required, email, unique case-insensitively
  password        Password::defaults(), confirmed
  username        optional; when absent derive it from business_name: lowercase,
                  keep only a-z and 0-9, drop leading digits, and if the result is
                  shorter than three characters, reserved, or already in
                  username_history, append a number starting at 2 until it passes.
                  Either way it goes through App\Rules\Username
  marketing_consent  optional boolean
Everything happens in one DB::transaction: the user (uuid is set by the model),
the account (name, username, defaults for vertical, country GB, locale en-GB,
currency GBP, timezone Europe/London, trial_ends_at now plus
config('billing.trial_days')), the account_settings row with features set to
config('features.defaults'), the account_user row with role owner and every
can_* toggle true and accepted_at now, and users.last_account_id set to the new
account. When marketing_consent is true, set marketing_consent_at to now and
marketing_consent_source to app_signup. If anything fails, nothing is written;
test that a rejected username leaves no user behind. Do not create an
entitlements row; that is the billing prompt.

Create config/billing.php with trial_days (30) and config/features.php with a
defaults map keyed by FeatureKey value: enquiries, agreements, invoicing and
payment_tracking true; intake_forms, automation, travel_estimates, photos and
feedback_requests false. A comment explains that this moves to the business logic
21.2 shape (enquiries only) when the settings toggles screen ships, per decision 78.

FEATURE KEYS, decision 78
Rename the cases in App\Enums\FeatureKey to exactly these nine, snake_case values:
enquiries, intake_forms, agreements, invoicing, payment_tracking, automation,
travel_estimates, photos, feedback_requests. In App\Services\Features, a key absent
from both maps is now off, not on; update the class comment. The demo seeder already
sets every case true and needs no change beyond whatever the rename breaks. Fix any
test that assumed the old default.

MARKETING CONSENT SOURCE
Add App\Enums\MarketingConsentSource with portal, app_signup and other, and a new
migration adding the check constraint users_marketing_consent_source_check from
the enum, the same way every other check constraint is built. Cast the column on
the User model.

TENANCY BINDING, decision 84
App\Http\Middleware\BindCurrentAccount, applied after auth:sanctum on every
authenticated API route. It resolves the account in this order: the user's
last_account_id if they still have an account_user row for it, otherwise their
only membership, otherwise their first membership by id. It calls
CurrentAccount::set() and, if the resolved account differs from last_account_id,
saves the new value. A user with no membership at all gets a 403 with a translation
key, not an exception. Register an alias for it in bootstrap/app.php and put the
authenticated routes in one group: ['auth:sanctum', 'account'].

MOBILE TOKENS, decision 84 and gaps 2 and 4
Hand-written, outside Fortify, under /api/auth:
  POST   /api/auth/token        email, password, device_name (required, max 80).
                                Validates the credentials the same way
                                authenticateUsing does, creates a personal access
                                token named after the device with an expiry of
                                config('sanctum.token_expiry_days') days, and
                                returns the plain-text token, its expiry and the
                                same payload as GET /api/me. Unauthenticated.
                                Throttled by a named limiter, five per minute
                                keyed on lowercased email plus IP, so Fortify's
                                login limiter has a twin here.
  GET    /api/auth/tokens       the caller's tokens: id, name, last_used_at,
                                expires_at, created_at, and which one is current.
  DELETE /api/auth/tokens/{id}  revoke one, the caller's own only.
  DELETE /api/auth/token        revoke the token that made the request. For a
                                session-cookie caller this is a 400 with a key
                                saying to use /logout.
Set config sanctum.expiration to the same number of days expressed in minutes, so
a token created anywhere without an explicit expiry still expires, and add a
token_expiry_days key to config/sanctum.php (365) with a comment referencing
decision 84. Schedule sanctum:prune-expired --hours=24 daily in routes/console.php.

ME
Replace the placeholder GET /api/user with GET /api/me, behind the authenticated
group, returning: the user (id, uuid, name, email, email_verified_at,
notification_preferences, marketing_consent_at), the current account (id, name,
username, vertical, country, locale, currency, timezone, profile_enabled,
trial_ends_at), the membership (role and the four can_* toggles) and features: the
map of every FeatureKey to the result of Features::enabled() for the account. Use
an API resource class rather than an array in a closure, and use the same resource
from the token endpoint.

USERNAME AVAILABILITY
GET /api/usernames/{username}, unauthenticated, throttled thirty per minute by IP.
Returns { available: bool, reason: null | invalid | reserved | taken }, where
taken covers both a live account and username_history. The reasons are codes for
the app to translate, not sentences. It runs the same App\Rules\Username as
registration, so the two cannot disagree.

RATE LIMITERS
All in FortifyServiceProvider next to the existing login limiter: token (above) and
forgot-password at three per minute keyed on lowercased email plus IP. Fortify's
verification routes already carry their own limiter from config, so leave those.
Apply the forgot-password limiter to Fortify's password.email route in whatever way
is plainest; if that is a small middleware matched on route name, that is fine. Say
what you chose.

TRANSLATION KEYS
Every message this prompt produces lives in lang/en-GB/auth.php or a new
lang/en-GB/account.php: the 403 for no membership, the 400 for revoking a session
with the token endpoint, validation messages that are not Laravel's own. Keep
Laravel's default validation messages in en-GB as they are.

ENVIRONMENT
FRONTEND_URL already exists in .env.example and .env; make sure config/app.php
exposes it as app.frontend_url and that nothing reads env() outside config/.
Uncomment CASHIER_MODEL=App\Models\Account in .env.example and delete the stale
comment above it, since the Account model exists and AppServiceProvider already sets
the customer model. Add any new variable to .env.example with a one-line comment,
and add nothing that can live in config instead.

TESTS, Pest 5, against klaroly_test as the existing suite does
- Registration: creates one user, one account, one account_settings row with
  config('features.defaults') as its features map, one owner account_user row with
  every toggle true, one username_history row, and sets last_account_id. A derived
  username from "Ellie Marsh Makeup" is elliemarshmakeup; from a name that
  collides, a number is appended; from a reserved word, it is not the reserved
  word. A rejected username rolls the whole thing back. marketing_consent true sets
  both consent columns, false or absent sets neither.
- Email case: register with Mixed@Example.com, the stored value is lowercase, and
  logging in as MIXED@EXAMPLE.COM succeeds on both the web login and the token
  endpoint.
- Web login: with the demo seed loaded, POST /login as ellie@example.com returns
  success, GET /api/me returns her account and username elliemarsh, and after that
  request CurrentAccount::id() equals her account id and Booking::count() equals the
  number of bookings the seeder created for her.
- Tenancy through auth: two accounts, each with bookings, created directly in the
  test. Log in as the owner of A and call GET /api/me. B's bookings are invisible
  through the Booking model, and A's are visible. Then log in as B's owner and
  assert the reverse. Then a user with no membership gets a 403.
- Token endpoint: a valid request returns a token that authenticates GET /api/me;
  a wrong password returns 422 with no hint which field was wrong; the sixth
  attempt in a minute returns 429; the token's expires_at is 365 days out; a
  session-cookie caller hitting DELETE /api/auth/token gets a 400.
- Device list: two tokens for one user; the list shows both and marks the current
  one; revoking the other leaves one; revoking a token belonging to a different
  user is a 404.
- Forgot password: known and unknown addresses produce byte-identical JSON and the
  same status; the fourth request in a minute is 429; a known address does send
  the notification (Notification::fake) and an unknown one does not.
- Password change and reset: both delete every token the user has, and the reset
  clears every session row for the user.
- Feature keys: Features::enabled() returns false for a key absent from both maps,
  true when the account map says so, and the booking override wins in both
  directions.
- Username availability: invalid, reserved, taken by a live account, taken by
  history, and available.
- The marketing_consent_source check rejects an invalid value with a
  QueryException.

FINALLY
1. composer lint.
2. php artisan migrate:fresh --seed against the local database, so the new
   constraint migration is proven against real rows.
3. Run the whole test suite, including the Prompt 3 tests.
4. Update the "Current state" and "Authentication shape" sections of the repository
   CLAUDE.md: what exists, the route list, the two credential types, the binding
   middleware and the rule that every authenticated route sits in the account group.
5. Add an "Authentication" section to README.md: how to log in locally as the demo
   account from the web app and with curl against the token endpoint, and where the
   verification and reset emails land (the log) when MAIL_MAILER=log.
6. Do NOT commit. Tell me it is done and list every file you created or changed.
7. Then tell me every place this prompt or the schema document was ambiguous, wrong,
   or would cause a problem later, and what you did about each. In particular, say
   what Prompt 5 (the Vue screens) needs to know about these endpoints that is not
   obvious from the route list.
```

**Check before you move on:** the route list it shows you first has nothing in it you
did not expect (say "go" only when it does), every test passes including the Prompt 3
suite, `git status` shows changes and no new commit, and read step 7. Anything it
flags gets folded into the schema document and the decision log before Prompt 5.
Then review, commit and push.

---

## Prompt 5: mobile authentication routes, API only

**When:** after the Prompt 4 commit is pushed and the tree is clean.
**Where:** open Claude Code at `~/Code/klaroly/klaroly`. It works in `api/`.
**Before you start:** decisions 87 and 90 in `decision-log.md` are what this prompt is
written from; the schema document's section 14 records what Prompt 4 found. This is a
short prompt on purpose. It exists because Prompt 4 found that a bearer-token caller
cannot register, reset a password or resend the verification email, and the screens
in Prompt 6 are written against routes that exist.

```
Add the mobile authentication routes to the Klaroly API. Work in api/. Read CLAUDE.md
(all of "Authentication shape"), docs/database-schema.md sections 5.2, 5.3 and 14,
routes/api.php, app/Http/Controllers/Auth/TokenController.php,
app/Http/Requests/Auth/IssueTokenRequest.php, app/Http/Resources/MeResource.php,
app/Http/Responses/UniformPasswordResetLinkResponse.php,
app/Http/Middleware/ThrottleForgotPassword.php, app/Actions/Fortify/CreateNewUser.php,
app/Actions/Fortify/ResetUserPassword.php, app/Providers/FortifyServiceProvider.php
and every test in tests/Feature/Auth/ before writing anything. Where this prompt and
the schema document disagree, the document wins, and you tell me where they
disagreed at the end.

FIRST, BEFORE ANY CODE
Having read all of that, list every route this prompt will register (method, path,
middleware), every file you will create or change, and every new config key,
translation key and environment variable. Then stop and wait for me to confirm. Do
not write code until I say go.

HOUSE RULES
Two-space indentation, except PHP at four spaces because Pint's default preset
enforces PSR-12. British English in comments and in every translation string. No
emoji. Plain code over clever code. No user-facing string may be a literal; keys in
lang/en-GB/ only, named for meaning rather than content.

DO NOT COMMIT. Do not stage, commit or push anything. Leave every change unstaged so
I can review each file in my Git browser. Tell me when the work is done and I will
commit.

WHY THIS PROMPT EXISTS, decision 87
Fortify's /register, /forgot-password, /reset-password and
/email/verification-notification routes sit in the web middleware group, so they
demand the CSRF cookie and, for the resend, a session. A bearer-token caller cannot
use any of them, and a Capacitor WebView posting from capacitor://localhost cannot
be relied on to hold the API's session cookie at all. This prompt adds four JSON
twins under /api/auth: stateless, outside the web group, no session and no CSRF.
They reuse Fortify's own actions and responses so the two paths cannot drift.
Fortify's routes are not changed in any way; they remain what the web app uses.
Profile and password update twins are NOT in this prompt; they arrive with the
settings screen.

SCOPE
Four routes, one migration, the tests, and the documentation for them. Nothing in
app/. Not in this prompt: profile and password update twins, passkeys, two-factor,
social sign-in, account switching, invitations, account deletion, client login.

ROUTES, all in routes/api.php
Unauthenticated, each with NormaliseEmail:

  POST /api/auth/register
    Throttled by a new named limiter, register, five per minute keyed on IP,
    defined in FortifyServiceProvider next to the others. The body is everything
    POST /register accepts (business_name, name, email, password,
    password_confirmation, username, marketing_consent) plus device_name, required,
    max 80, validated the same way IssueTokenRequest validates it. Run the existing
    CreateNewUser action unchanged, fire Illuminate\Auth\Events\Registered so the
    verification email goes exactly as Fortify sends it, do NOT log anyone into a
    session, bind the new account as the current account so MeResource can read it,
    issue a personal access token exactly as POST /api/auth/token does (device name,
    expiry from config), and return the token endpoint's shape, {token, expires_at,
    me}, with a 201. Validation failures are Laravel's standard 422. Move the
    token-issuing code out of TokenController into one place both controllers
    call, so there is one place a token is minted; a small service under
    App\Services is fine. Say what you named it.

  POST /api/auth/forgot-password
    Throttled by the existing forgot-password limiter, applied directly as route
    middleware rather than through the route-name matcher (leave that middleware in
    place for Fortify's route). Body: email. Send the reset link through the password
    broker the way Fortify's PasswordResetLinkController does, and return exactly the
    body and status the web route returns, whether or not the address is known.
    Reuse UniformPasswordResetLinkResponse or whatever it wraps; do not write a
    second message.

  POST /api/auth/reset-password
    No limiter beyond the platform's, matching Fortify's own reset route; say if you
    think it needs one. Body: token, email, password, password_confirmation. Reset
    through the password broker with the existing ResetUserPassword action, which
    already revokes every token and every session. Success is 200 with the same
    {message} body as the web route. A bad or expired token is a 422 with the error on
    email, the same as the web route. It does not log anyone in and issues no token;
    the phone signs in again with the new password, as the web does.

Inside the existing ['auth:sanctum', 'account'] group:

  POST /api/auth/email/verification-notification
    Throttled six per minute like Fortify's. If the user is already verified, 204
    with no body. Otherwise send the verification notification and return 202 with
    no body. Both match Fortify's JSON responses.

Update the comment at the top of routes/api.php so it says which flows have twins
and why.

DEPOSIT PERCENT DEFAULT, decision 90
A new migration gives account_settings.deposit_percent a default of 25, so a bare
insert passes the deposit rule check. Remove the named constant in CreateNewUser
and let the database default apply. The existing test that a fresh account has 25
percent must still pass; add one that inserts an account_settings row with only
account_id and asserts it is accepted.

TRANSLATION KEYS
Add any new message to lang/en-GB/auth.php. There should be almost none; the twins
return Fortify's existing messages.

TESTS, Pest, against klaroly_test as the existing suite does
- Register twin: a valid request returns 201 with a token that authenticates
  GET /api/me, me matches what GET /api/me returns for the new user, the response
  sets no session cookie, exactly one user, account, account_settings,
  account_user and username_history row exist, and the verification notification
  was sent (Notification::fake). A missing device_name is a 422. The sixth attempt
  in a minute is a 429. Mixed@Example.com is stored lowercase. A rejected username
  rolls everything back and no token exists.
- Forgot-password twin: known and unknown addresses produce byte-identical JSON and
  the same status, and that JSON is byte-identical to what POST /forgot-password
  returns. The fourth request in a minute is 429. A known address sends the
  notification and an unknown one does not.
- Reset-password twin: with a real token from the broker, 200, the password is
  changed, every token the user had is gone and every session row is gone. A wrong
  token is a 422 on email. The response contains no token.
- Resend twin: an unverified bearer caller gets 202 and the notification is sent; a
  verified one gets 204 and nothing is sent; a session-cookie caller gets the same
  answers.
- Both controllers mint tokens through the same service: one test that the token
  from the register twin has the same name and expiry as one from the token endpoint
  for the same device_name.
- deposit_percent: the bare insert test above.

FINALLY
1. composer lint.
2. php artisan migrate:fresh --seed against the local database, so the new default
   migration is proven against real rows.
3. Run the whole test suite, including the Prompt 3 and Prompt 4 suites.
4. Update the route tables and "The rules" in the repository CLAUDE.md, and the
   Authentication section of README.md with a curl example for the register twin.
5. Do NOT commit. Tell me it is done and list every file you created or changed.
6. Then tell me every place this prompt or the schema document was ambiguous, wrong,
   or would cause a problem later, and what you did about each. In particular, say
   what Prompt 6 (the Vue screens) needs to know about these four routes that is not
   obvious from the route list.
```

**Check before you move on:** the route list it shows you first has exactly the four
routes and nothing else (say "go" only when it does), every test passes including the
earlier suites, `git status` shows changes and no new commit, and read step 6. Anything
it flags gets folded into the schema document and the decision log before Prompt 6.
Then review, commit and push.

---

## Prompt 6: the authentication screens

**When:** after the Prompt 5 commit is pushed and the tree is clean.
**Where:** open Claude Code at `~/Code/klaroly/klaroly`. It works in `app/`.
**Before you start:** decisions 84, 87, 88 and 89 are what this prompt is written
from, and it is written against the routes the API actually registers. Herd must be
serving `api.klaroly.test` and proxying `app.klaroly.test` to Vite, and the local
database must be seeded, so the live check at the end can run. The mobile target is
built and tested but cannot be run on a device yet; Capacitor arrives once these
screens and one list view exist.

```
Build the authentication screens for the Klaroly web app. Work in app/. Read
CLAUDE.md fully, especially "Authentication shape", "App rules" and the route tables,
then every file under app/src (it is small), app/package.json, app/vite.config.ts,
app/eslint.config.js and the Authentication section of README.md, before writing
anything. The API is the authority on every path, field and status code; where this
prompt and the API disagree, the API wins, and you tell me where they disagreed at
the end.

FIRST, BEFORE ANY CODE
Having read all of that, list every app route you will add, every file you will
create or change, every package you will add, and every new locale key. Then stop
and wait for me to confirm. Do not write code until I say go.

HOUSE RULES
Two-space indentation. No semicolons. British English in every string and comment.
No emoji. Plain code over clever code. Single-file component block order is
template, then script, then style. Every user-facing string is a key in
src/locales/en-GB.json, named for meaning rather than content. Every colour, font,
size and radius comes from the @theme block in src/assets/app.css; no hardcoded
values, and do not invent brand colours, the placeholders stay until the brand work
lands. No UI component framework. Nothing outside src/lib/platform.ts inspects the
platform; other code imports its booleans. Nothing outside src/lib/api.ts calls
fetch. The API base URL is import.meta.env.VITE_API_URL and is never derived from
window.location.

DO NOT COMMIT. Do not stage, commit or push anything. Leave every change unstaged so
I can review each file in my Git browser. Tell me when the work is done and I will
commit.

SCOPE
Sign in, register, forgot password, reset password, sign out, the verification
banner with resend, the verified landing, and session restore when the app loads,
on both build targets. Nothing in api/. Not in this prompt: the device list, profile
and password change, two-factor, passkeys, Sign in with Apple or Google, account
switching, invitations, account deletion, an app shell or navigation, and
persistent token storage on native (see below).

HOW THE API BEHAVES, so nothing here is guessed
Every request sends Accept: application/json; without it Fortify redirects instead
of answering. On web every request sends credentials: include, and every non-GET
sends X-XSRF-TOKEN read from the XSRF-TOKEN cookie, URL-decoded, read fresh on
every request because the token rotates after login and register. GET
/sanctum/csrf-cookie must be called once before the first non-GET. On native there
is no cookie; the Authorization: Bearer header carries the token.

Web routes, session cookie, Fortify:
  POST /login              email, password, optional remember (boolean). 200 with
                           {"two_factor": false}. Wrong credentials: 422 with an
                           error on email only, never password. Sixth attempt in a
                           minute: 429.
  POST /register           business_name, name, email, password,
                           password_confirmation, optional username, optional
                           boolean marketing_consent. 201 with an empty body and the
                           browser is already signed in. Validation errors are
                           Laravel's {message, errors} shape; password minimum is
                           ten characters and, outside testing, must not appear in a
                           known breach, so a password error can arrive from the API
                           for a value the app thought was fine.
  POST /logout             204.
  POST /forgot-password    email. Always 200 with {message}, known or unknown, so
                           the screen shows one confirmation regardless. Fourth
                           request in a minute: 429.
  POST /reset-password     token, email, password, password_confirmation. 200 with
                           {message}. A bad or expired token: 422 on email. A reset
                           does not sign the person in.
  POST /email/verification-notification   202 sent, 204 already verified, 429 on
                           the seventh in a minute.
Native routes, bearer token, hand-written; same bodies, same statuses, except:
  POST /api/auth/token     email, password, device_name. 200 with {token,
                           expires_at, me}. Replaces /login.
  POST /api/auth/register  the /register body plus device_name. 201 with {token,
                           expires_at, me}. Replaces /register.
  DELETE /api/auth/token   204. Replaces /logout.
  POST /api/auth/forgot-password, POST /api/auth/reset-password and
  POST /api/auth/email/verification-notification replace their web twins.
Both targets:
  GET /api/me              200 with {data: {user, account, membership, features}}.
                           401 means signed out. 403 with {message} means the user
                           belongs to no account. user.notification_preferences is
                           an empty array when the stored map is empty, so treat it
                           as object-or-empty-array and normalise it to an object.
  GET /api/usernames/{username}   {available, reason} where reason is null,
                           invalid, reserved or taken. Thirty per minute. The rule
                           accepts only lowercase, so lowercase before sending.
Links in emails: the reset link is FRONTEND_URL/reset-password?token=...&email=...
and the verification link lands on FRONTEND_URL/?verified=1.

PACKAGES
Add vitest and happy-dom as dev dependencies, and a "test": "vitest run" script.
Nothing else. No component testing library, no HTTP mocking library; vi.fn on
globalThis.fetch is enough.

src/lib/api.ts
Keep its shape and its rules. Add:
- On web, before any non-GET, call ensureCsrfCookie() if the XSRF-TOKEN cookie is
  absent. If a non-GET on web comes back 419, fetch the cookie once and retry the
  request once. Never retry anything else.
- A way for the auth store to hear about a 401 from an authenticated call:
  onUnauthenticated(handler), one handler, called after the ApiError is built and
  before it is thrown. Keep it plain.
- ApiError gains validationErrors(): a map of field to first message for a 422, and
  an empty map otherwise, so screens do not dig through the body.
- The bearer token comes from src/lib/tokenStorage.ts, not from a module variable.

src/lib/tokenStorage.ts
get(), set(token), clear(). The implementation is in memory. A comment at the top
says that on a device the token must survive a relaunch, that this file is replaced
by Capacitor's secure storage when Capacitor is added, and that nothing else in the
codebase changes when it is. Until then a mobile build forgets its login on reload,
and that is accepted.

src/lib/platform.ts
Add deviceName(): a short human label for the device list, isIOS ? 'iPhone or iPad'
: isAndroid ? 'Android' : 'Mobile'. It is the one string in the app that is not a
locale key, because it is stored by the API and shown back on every device, so it
must not change with the viewer's language. Say so in a comment. Capacitor's Device
plugin replaces the body later.

src/lib/auth.ts
The one module that knows web signs in with a session and native signs in with a
token. It imports isNative and deviceName from platform.ts and is the only file
besides api.ts that may branch on the platform; add that sentence to CLAUDE.md.
It exports:
  signIn(email, password, remember)   web: POST /login then GET /api/me.
                                      native: POST /api/auth/token, store the
                                      token, return me from the response.
  register(fields)                    web: POST /register then GET /api/me.
                                      native: POST /api/auth/register with
                                      device_name, store the token, return me.
                                      Sends password_confirmation equal to
                                      password (decision 88).
  signOut()                           web: POST /logout. native: DELETE
                                      /api/auth/token, then clear the token.
                                      Either way, clear local state even if the
                                      request fails.
  fetchMe()                           GET /api/me, normalising
                                      notification_preferences.
  forgotPassword(email), resetPassword(token, email, password),
  resendVerification()                each choosing the web or native path.
  checkUsername(username)             GET /api/usernames/{username}.
Every function returns typed data from src/types/auth.ts (Me, User, Account,
Membership, FeatureKey and the feature map) and throws ApiError on failure. No
screen imports api.ts directly; screens call the store, the store calls this.

src/stores/auth.ts
Replace the placeholder. State: me (Me | null) and status, one of unknown,
signed_out, signed_in, plus notice, an optional locale key for a message the login
screen should show once (used for "no account" and for "you are signed out"). Has:
  bootstrap()   called once, before the first navigation. Calls fetchMe(). 200
                sets signed_in. 401 sets signed_out. 403 signs out through
                signOut() and sets notice to account.no_membership. On native with
                no stored token it sets signed_out without a request. Any other
                failure sets signed_out and rethrows nothing; the app must still
                load.
  signIn, register, signOut, refresh   thin wrappers that set me and status.
Register api.onUnauthenticated so a 401 anywhere sets signed_out and clears me;
the router guard then does the redirect.

src/router/index.ts
Routes, all listed by hand: /login, /register, /forgot-password and /reset-password
with guestOnly; / (dashboard) with requiresAuth; /billing web-only exactly as it is
now. The guard awaits auth.bootstrap() the first time it runs and never again.
Unauthenticated on a requiresAuth route: go to login with redirect set to the full
path, as now. Signed in on a guestOnly route: go to the dashboard. After a
successful sign-in or registration follow the redirect query only when it is a
relative path (starts with a single slash, not two); otherwise go to the dashboard.
Say why in a comment.

SCREENS, all under src/views, using small shared components under src/components
(a text field with label, hint and error; a password field with a show-password
toggle; a submit button that disables while pending; a form-level error line; the
centred card that wraps every auth screen). Every field has a label, autocomplete
set correctly (email, current-password, new-password, organization for the business
name, name, username), aria-invalid and aria-describedby wired to its error, and the
first field with an error receives focus after a failed submit. Enter submits. A
form submits once while pending. Layout works from phone width up with no
breakpoint-specific markup; it is one column either way (decision 10).

LoginView. Email, password, on web a "keep me signed in" checkbox ticked by default
which sends remember, links to register and forgot password. Shows store.notice
once and clears it. 422: the email error under the email field, and clear the
password field. 429: auth.too_many_attempts, a locale key, not the API's sentence.
Any other failure: auth.request_failed.

RegisterView. Business name, your name, email, username, password (one field,
show-password toggle), marketing consent checkbox unticked by default, submit, link
to sign in. Username: as the person types the business name and has not typed in
the username field, show the derived name (lowercase, keep a-z and 0-9, drop
leading digits) as the field's value, in a helper under src/lib/username.ts with
a comment that the API's CreateNewUser is the authority and this is a preview. A
hint under the field shows what it becomes, which is the username followed by
.klaroly.com. Once the username has three characters, check availability 300 ms
after the last keystroke and show the result as a locale key per reason
(auth.username_available, auth.username_invalid, auth.username_reserved,
auth.username_taken); ignore a stale response that arrives after a newer
request. Always lowercase what is sent. Send username only when it is non-empty.
On success go to the dashboard; the verification banner shows there. 422: each
error under its field, including a password error from the breach check.

ForgotPasswordView. Email, submit. On 200 replace the form with a confirmation
(auth.reset_link_sent) that does not say whether the address was known. 429:
auth.too_many_attempts.

ResetPasswordView. Reads token and email from the query. If either is missing,
show auth.reset_link_invalid with a link to forgot password and no form. Otherwise
show the email read-only, one new-password field with the toggle, submit. On 200
show auth.password_reset_done with a link to sign in; the person is not signed in.
422 on email means the token is bad or expired: show auth.reset_link_invalid with
the forgot-password link. Any other error under the password field.

VerificationBanner (component). Shown on the dashboard when me.user.email_verified_at
is null. Says the address is unverified, with a resend button. Resend: 202 shows
auth.verification_sent; 204 refreshes me and the banner disappears; 429 shows
auth.too_many_attempts.

DashboardView. Keep the empty state. Add the banner, a greeting that uses
me.account.name, and a sign-out button that calls store.signOut() and goes to
login. If the route has ?verified=1, refresh me, show auth.email_verified once, and
replace the query so a reload does not show it again.

TESTS, Vitest with happy-dom, under src with a .test.ts suffix beside the file they
test. Mock fetch with vi.fn on globalThis. To test the native branch, vi.mock
'@/lib/platform' in that test file; that is the approved way and the only way.
- api.ts: every request carries Accept: application/json. On web: credentials
  include, X-XSRF-TOKEN is the URL-decoded cookie, a non-GET with no cookie fetches
  /sanctum/csrf-cookie first, a 419 on a non-GET fetches the cookie and retries
  exactly once, a 419 on the retry throws. On native: Authorization Bearer from
  tokenStorage, credentials omit, no CSRF call ever. 204 resolves to null. A non-ok
  response throws ApiError with the parsed body, and validationErrors() maps a 422.
  A 401 calls the onUnauthenticated handler before throwing.
- auth.ts: on web signIn posts /login then gets /api/me; on native it posts
  /api/auth/token with device_name, stores the token and returns me from the
  response. register sends password_confirmation equal to password on both. signOut
  clears the token on native even when the request fails. fetchMe turns an empty
  array notification_preferences into an empty object. checkUsername lowercases.
- stores/auth.ts: bootstrap sets signed_in on 200, signed_out on 401, signed_out
  with notice account.no_membership on 403 and calls signOut; on native with no
  token it makes no request.
- router: waits for bootstrap once, sends an unauthenticated visitor to login with
  redirect, sends a signed-in visitor away from guest-only routes, follows a
  relative redirect and refuses //evil.example.
- username.ts: "Ellie Marsh Makeup" gives elliemarshmakeup, "123 Studio" gives
  studio, an empty result stays empty.

FINALLY
1. npm run lint, npm run typecheck, npm run build and npm run build:mobile all
   clean. Confirm the mobile bundle contains no billing chunk, as before.
2. npm test passes.
3. Live check against Herd, with the API seeded: sign in as ellie@example.com and
   land on the dashboard with no banner (the demo account is verified); sign out;
   register a new account with a fresh address and see the banner; resend and find
   the email in api/storage/logs/laravel.log; open the verification link in the
   same browser and see the verified message; sign out; forgot password for the
   new address, take the link from the log, reset, sign in with the new password;
   sign in with a wrong password and see the error on the email field; visit
   /billing while signed out and be returned there after signing in. Report each
   step's result.
4. Update the repository CLAUDE.md: "App rules" gains src/lib/auth.ts,
   src/lib/tokenStorage.ts and the testing rule (vi.mock on platform.ts), and
   "Current state" describes what the app now has. Update README.md "App setup"
   with npm test and the flow for signing in locally.
5. Do NOT commit. Tell me it is done and list every file you created or changed.
6. Then tell me every place this prompt, CLAUDE.md or the API was ambiguous, wrong,
   or would cause a problem later, and what you did about each. In particular, say
   what the Capacitor prompt needs to know about tokenStorage.ts, deviceName() and
   the verification link, and what the first list view needs to know about the
   store, the guard and the 401 handling.
```

**Check before you move on:** the file list it shows you first has nothing in it you
did not expect (say "go" only when it does), lint, typecheck, both builds and the tests
pass, the live check in step 3 reports every step, `git status` shows changes and no
new commit, and read step 6. Anything it flags gets folded into the decision log before
the next prompt. Then review, commit and push.

# Klaroly: Claude Code Prompts

**Date:** 1 September 2026, Prompt 3 rewritten 2 September 2026
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
> **Do not name any script `deploy`.** Use `ship`. It reads the same and avoids a
> collision with a reserved command if the package manager ever changes.
>
> **Local environment already in place:** Laravel Herd with PHP 8.4, and
> Postgres 17 via Postgres.app on `127.0.0.1:5432`. Do not install PHP, a web
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

## Prompt 4: authentication

**When:** evenings 6 and 7. Written out when you get there, because it should be
written against the schema that actually exists rather than the one specified above.
The five Fortify gaps from section 5 of the technical proposal are the part not to
lose: uniform forgot-password response, Sanctum token expiry plus pruning, revoke
tokens on password change, rate limit the mobile token endpoint, and
Password::uncompromised(). Two things the repository already has that the prompt
should not redo: Fortify's two-factor columns and the passkeys table are migrated,
and the billable model is `Account`, not `User`, so Cashier's published migrations
will need altering when the billing prompt arrives.

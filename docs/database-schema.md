# Klaroly database schema, version 1

**Status:** signed off and migrated, 2 September 2026 (Prompt 3, commit `c8213fc`). Written for Postgres 17 or later (18 locally), Laravel 13, Cashier 16, Fortify and Sanctum. Section 13 records what the first migration set found and how each point was settled, section 14 does the same for the authentication prompt (Prompt 4, 2 September 2026), and section 15 for the mobile authentication routes (Prompt 5, 2 September 2026); the tables below already reflect those answers.

**Scope:** the first build as settled in decision 74, held to the rules in decisions 44, 55, 71, 72, 73 and 75. Section 7 designs the tables that are not migrated yet, so that the first migration set leaves room for them.

---

## 1. What this document is

The single description of every table and column, what it is for, and the rules that produced it. Prompt 3 was written from here and the migrations match it. When a later feature prompt adds a table or a column, it is added here first.

It is deliberately written as a specification rather than as migration code, because the decisions are what need reviewing. The Laravel-specific mechanics are collected in section 10 so they do not clutter the tables.

---

## 2. Rules that apply to every table

These are settled. The tables in sections 5 to 7 follow them without restating them.

**Tenancy: `account_id` on every customer-data table** (decision 44). Including tables that also carry `booking_id`. One global scope filters everything without a join. The exceptions are `accounts` itself, `users`, `identities` and the framework tables. `username_history` carries `account_id` but is not globally scoped, because the hostname lookup that reads it runs before any account context exists. A Pest test asserts that two accounts cannot see each other's bookings.

**Names describe the job, never the industry** (decision 73, technical proposal 15.5a). No table, column, model, enum, route or event named `bride`, `bridal`, `wedding`, `groom`, `trial` or `makeup`. The test: would the name still make sense on a DJ's booking? The rule is about names, not values (decision 81): `trial` as an event type, a service `applies_to` value or a template key is data the artist sees, and a DJ's booking simply never holds it.

**Money is a `bigint` integer in the currency's ISO 4217 minor unit, with a `_minor` suffix** (decision 77). Pence, cents and euro cents are all the minor unit; the column never says which country. No floats, no decimals, no exceptions. Percentages are whole-number `smallint`. Every money column sits beside a `currency` or inherits one from its booking, and the dashboard never sums across currencies (technical proposal 15.2).

**Two models of time, on purpose** (technical proposal 15.4). Events are local wall clock plus an IANA zone: `event_date date`, `start_time time`, `timezone varchar`. Anything that fires, and every audit, signature or financial timestamp, is `timestamptz` in UTC. Laravel's `created_at` and `updated_at` are `timestamptz` throughout. A comment at the top of the events migration explains the split.

**Enum-like columns are `varchar` with a check constraint**, never a Postgres enum type, each backed by a PHP enum. Values are `snake_case`.

**Primary keys are `bigint`** via `$table->id()`. Random tokens exist only where a URL leaves the app (section 7, `client_links`), and those are UUIDv4 or raw random bytes, never UUIDv7 (decision 22). Offline capture is protected by an idempotency key on the create request, which is a header, not a column.

**Soft deletes only where a record has a life after deletion**: `accounts`, `users`, `contacts`, `bookings`, `services`. Everything else is a hard delete, recorded by the activity trail when that exists. Payments in particular are hard-deleted, because "reversible" means the row goes and the balance recalculates (decision 27).

**JSONB for shapes that vary or that are snapshots**, never for data that is queried across accounts: feature toggles, line snapshots on quotes and invoices, template variants, notification preferences.

**Foreign key actions.** `account_id` cascades. `booking_id` cascades to its children (events, party members, contacts, lines) because a booking is only ever soft-deleted in practice and the cascade exists for the genuine delete of a dead enquiry. Rate card references (`service_id`) set null, because a deleted service must not orphan a booking. `contact_id` on bookings restricts, because a contact with bookings cannot be deleted.

**Structure now, screen later** (decision 75). Settled columns for later features are present and unused. Whole tables for unsettled features are designed in section 7 and not migrated.

---

## 3. Open calls, taken as recommended

Six calls from earlier in the thread were not explicitly answered. This document takes the recommendation for each. Any of them can be changed before the first migration runs.

| Call | Taken |
|---|---|
| Contacts or clients | `contacts`. Matches the business logic doc and avoids colliding with Stripe's and Sanctum's use of "client" |
| One invoice or two | One invoice per booking by default, carrying a deposit amount and two due dates. A second invoice can be raised manually |
| Where the price lives | Live, editable `booking_lines` on the booking; `quotes` is an immutable snapshot with an outcome |
| Party and lines | Independent. A one-tap action builds lines from the party; a line can also be added without a member |
| Primary keys | `bigint`, per section 2 |
| Where the artist goes | One `location_type` and one address per event, which travel is computed to; nullable `venue_name` and `venue_address` for when the occasion's venue differs |

---

## 4. The map

```
accounts ─────────────── account_settings (1:1)
 │  ├── account_user ─── users ─── identities
 │  ├── username_history
 │  ├── services                     the rate card
 │  ├── message_templates            account overrides (account_id set, booking_id null)
 │  ├── contract_templates           account overrides (account_id set)
 │  ├── entitlements
 │  └── contacts ─── (user_id, later: the client's own login)
 │        └── bookings               enquiry through to closed, one table
 │              ├── events           main, trial, consultation, ...
 │              ├── party_members
 │              ├── booking_contacts
 │              ├── booking_lines    the live price
 │              ├── quotes           immutable snapshots with an outcome
 │              ├── invoices ─── payments
 │              ├── agreements       versions, never edited once signed
 │              ├── notes
 │              ├── message_templates   per-booking overrides (booking_id set)
 │              └── booking_user     collaborators on this booking (empty in v1)
 │
 ├── system rows: message_templates and contract_templates with account_id null
 └── framework: subscriptions, subscription_items, personal_access_tokens, sessions, jobs
```

---

## 5. The first migration set

Twenty-two tables plus framework tables. Types are Postgres types. `ts` means `timestamptz`. Every table has `created_at ts` and `updated_at ts` unless noted.

### 5.1 `accounts`

The business. The tenant. One per artist today; a user may belong to several later.

```
id                  bigint      pk
name                varchar(120) not null            the business name shown to clients
username            varchar(63)  not null unique     lowercase, ^[a-z][a-z0-9]{2,62}$, check constraint
vertical            varchar(40)  not null default 'wedding_makeup'
country             char(2)      not null default 'GB'      ISO 3166-1 alpha-2
locale              varchar(10)  not null default 'en-GB'   BCP 47
currency            char(3)      not null default 'GBP'     ISO 4217
timezone            varchar(64)  not null default 'Europe/London'   IANA, for reminder arithmetic
profile_enabled     bool         not null default false     decision 55: off means 302 to www, on means render
stripe_id           varchar(255) nullable unique            Cashier
pm_type             varchar(255) nullable                   Cashier
pm_last_four        varchar(4)   nullable                   Cashier
trial_ends_at       ts           nullable                   Cashier
created_at, updated_at, deleted_at
```

Notes. `username` is stored lowercase and validated against the reserved list in `config/reserved_usernames.php`, against `username_history`, and against the regex, in that order. `country` and `locale` are both needed and are different things (technical proposal 15.1). The Cashier columns are here rather than on `users` because the account is the billable entity: `Cashier::useCustomerModel(Account::class)` and the published Cashier migration targets `accounts`. Postgres compares `stripe_id` case-sensitively by default, which is what Stripe wants. `trial_ends_at` is Cashier's own column name and is the one exception to the naming rule in decision 73, tolerated because renaming it means patching the package.

### 5.2 `account_settings`

One row per account, created with it. Everything the artist sets once and overrides per booking. Kept off `accounts` so that identity and tenancy stay small and settings can grow.

```
id                          bigint   pk
account_id                  bigint   not null unique, fk accounts cascade
features                    jsonb    not null default '{}'     feature key -> bool, decision 74
deposit_type                varchar(10) not null default 'percent'   check: fixed | percent
deposit_amount_minor        bigint   nullable                  when fixed
deposit_percent             smallint nullable default 25       when percent, whole number; default from decision 90
deposit_due_days            smallint not null default 7        days after the invoice is issued, decision 79
balance_due_days_before     smallint not null default 28       days before the main event
hold_days                   smallint not null default 14       how long a date is held once pencilled in
payment_instructions        text     nullable                  bank details, a link, a sentence
invoice_prefix              varchar(10) not null default 'INV'
next_invoice_number         int      not null default 1        incremented under row lock at issue
legal_name                  varchar(160) nullable              on invoices and agreements
address_line_1              varchar(120) nullable
address_line_2              varchar(120) nullable
city                        varchar(80)  nullable
postcode                    varchar(12)  nullable
tax_number                  varchar(30)  nullable              VAT or equivalent, shown on invoices if set
base_postcode               varchar(12)  nullable              travel origin
travel_charging             varchar(10) not null default 'included'   check: included | radius | per_mile | flat
travel_free_radius_miles    smallint nullable
travel_rate_per_mile_minor  bigint   nullable default 45        bigint like every _minor column
travel_flat_fee_minor       bigint   nullable
early_start_before          time     nullable                  early start supplement threshold
business_year_start_month   smallint not null default 4        UK tax year by default
business_year_start_day     smallint not null default 6
created_at, updated_at
```

Notes. `features` is read only through `App\Services\Features::enabled()`, decision 74. The keys are the nine in `App\Enums\FeatureKey`: `enquiries`, `intake_forms`, `agreements`, `invoicing`, `payment_tracking`, `automation`, `travel_estimates`, `photos`, `feedback_requests`. A key that is absent from the map is **off** (decision 78), so `{}` is a bare account; registration writes the default map from `config/features.php` rather than relying on absence. The entitlement check inside `enabled()` returns true until the billing prompt. Check constraint: `deposit_type = 'fixed'` requires `deposit_amount_minor`, `'percent'` requires `deposit_percent`. Because `percent` is the default type, `deposit_percent` carries a default of 25 so that a bare insert passes the check (decision 90; the default migration landed in Prompt 5 and registration no longer writes the value). `hold_days` is business logic 5.1's soft hold and 5.3's real one, written by `App\Services\SoftHold` on a stage change and read by the waiting-on axis. It is a column rather than a config entry because its shape is settled even though its number is not: it describes the business, more than one screen reads it, and it is structurally identical to the two day counts above it. Fourteen days is the courtesy hold this trade runs on, and it is deliberately not `cold_enquiry_days`, which is 21 and asks a different question. Default working hours from business logic section 24 are deferred; they need a shape decision first.

### 5.3 `users`

A person with a login. Only person things live here (decision 71). Being an artist is an `account_user` row; being a client is a `contacts.user_id` link.

```
id                          bigint   pk
uuid                        uuid     not null unique            Apple appAccountToken and Google obfuscatedAccountId, later
name                        varchar(120) not null
email                       varchar(255) not null              unique index on lower(email); stored lowercase, normalised on the way in
password                    varchar(255) nullable              nullable from day one: a future Apple user may never set one
email_verified_at           ts       nullable
remember_token              varchar(100) nullable
two_factor_secret           text     nullable                  Fortify
two_factor_recovery_codes   text     nullable                  Fortify
two_factor_confirmed_at     ts       nullable                  Fortify
notification_preferences    jsonb    not null default '{}'     per type, business logic 27
marketing_consent_at        ts       nullable                  decision 71: a dated fact, never a bool
marketing_consent_source    varchar(40) nullable               check: portal | app_signup | settings | other (constraint added in Prompt 4, widened in Prompt 7)
last_account_id             bigint   nullable fk accounts set null   which account to open on next login
created_at, updated_at, deleted_at
```

Notes. Face ID app lock is a device setting and never reaches the database. Never look up a user by email for provider sign-in; that goes through `identities` (decision 28). The `lower(email)` index rejects a second row that differs only in case, but Fortify's login lookup compares the column as stored, so authentication lowercases and trims email on registration, login, password reset and profile update; the index is the backstop, not the mechanism. A soft-deleted user's email cannot be reused at registration, because both the unique validation and the index see deleted rows; decision 91 defers the answer to the account deletion work.

### 5.4 `account_user`

Membership. One owner per account, any number of collaborators later.

```
id                  bigint   pk
account_id          bigint   not null fk accounts cascade
user_id             bigint   not null fk users cascade
role                varchar(20) not null       check: owner | collaborator
can_edit            bool     not null default false
can_see_prices      bool     not null default false
can_see_invoices    bool     not null default false
can_see_contacts    bool     not null default true
invited_by_user_id  bigint   nullable fk users set null
invited_at          ts       nullable
accepted_at         ts       nullable
created_at, updated_at
unique (account_id, user_id)
partial unique (account_id) where role = 'owner'
```

Notes. Whether a collaborator is an assistant or a second artist is a matter of which toggles are on, not a different role (business logic 20.2). The owner row has every toggle true by convention and policies short-circuit on `role = 'owner'`.

### 5.5 `identities`

Sits empty until Sign in with Apple and Google arrive together.

```
id                  bigint   pk
user_id             bigint   not null fk users cascade
provider            varchar(20) not null       check: apple | google
provider_user_id    varchar(255) not null      Apple sub, Google sub
provider_email      varchar(255) nullable
email_is_private    bool     not null default false    Apple Hide My Email
created_at, updated_at
unique (provider, provider_user_id)
```

### 5.6 `username_history`

Every username an account has ever held, including the current one. Never reclaimable by anyone else.

```
id              bigint   pk
account_id      bigint   not null fk accounts cascade
username        varchar(63) not null unique
claimed_at      ts       not null
released_at     ts       nullable      null means current
```

Notes. No `updated_at`. The unique constraint across all history is what enforces "nothing released is ever reclaimable" (business logic 4.7). A released username on the artist hostname redirects with a 302 to the account's current one (decision 55).

### 5.7 `contacts`

The person who books and pays. Name, email, phone, address, and nothing else. Reusable across bookings when the same family books again.

```
id              bigint   pk
account_id      bigint   not null fk accounts cascade
user_id         bigint   nullable fk users set null     decision 71, unused in v1
first_name      varchar(80)  not null
last_name       varchar(80)  nullable
email           varchar(255) nullable
phone           varchar(30)  nullable
address_line_1  varchar(120) nullable
address_line_2  varchar(120) nullable
city            varchar(80)  nullable
postcode        varchar(12)  nullable
country         char(2)      nullable
created_at, updated_at, deleted_at
index (account_id, last_name, first_name)
index (account_id, email)
```

Notes. First and last name are separate because merge fields need the first name on its own. No dates of birth anywhere in the schema, for anyone (business logic 4.2). Contact rows are never a marketing list (decision 71).

### 5.8 `bookings`

One table from first enquiry to closed. Every stage lives here; the interface shows enquiries and bookings as two lists filtered on `stage`.

```
id                          bigint   pk
account_id                  bigint   not null fk accounts cascade
contact_id                  bigint   not null fk contacts restrict
stage                       varchar(20) not null default 'new'
                              check: new | in_conversation | possible | quoted |
                                     provisional | confirmed | completed | closed |
                                     lost | cancelled
source                      varchar(30) nullable
                              check: manual | web_form | forwarded_email | voice_note |
                                     captured_at_event | other
source_booking_id           bigint   nullable fk bookings set null    captured at which job
enquiry_message             text     nullable                          the original message, if any
lost_reason                 varchar(40) nullable
lost_at                     ts       nullable
converted_at                ts       nullable        enquiry became provisional
confirmed_at                ts       nullable        signed and deposited
cancelled_at                ts       nullable
cancellation_reason         text     nullable
hold_expires_at             date     nullable        provisional hold, "not held" after this
last_touched_at             ts       not null        any change, note or message on the booking
currency                    char(3)  not null        defaults from the account in the model, never in the schema
pricing_mode                varchar(10) not null default 'itemised'   check: itemised | fixed
fixed_price_minor           bigint   nullable        when fixed
fixed_price_description     varchar(200) nullable
discount_type               varchar(10) nullable    check: amount | percent
discount_value              int      nullable        minor units when amount, whole number when percent
discount_reason             varchar(120) nullable
deposit_override_minor      bigint   nullable        overrides the account rule for this booking
deposit_override_percent    smallint nullable
photo_consent               varchar(20) nullable    check: none | private | social    business logic 9.2, unused in v1
photo_consent_recorded_at   ts       nullable
gallery_url                 varchar(500) nullable   decision 73 rename
gallery_received_on         date     nullable
access_pin                  varchar(255) nullable   encrypted cast, decision 72
access_pin_changed_at       ts       nullable
feature_overrides           jsonb    not null default '{}'    only explicitly overridden keys
created_by_user_id          bigint   nullable fk users set null
created_at, updated_at, deleted_at
index (account_id, stage)
index (account_id, last_touched_at)
index (account_id, contact_id)
index (source_booking_id)
```

Notes. Enquiries are `stage in (new, in_conversation, possible, quoted)`. Bookings are `stage in (provisional, confirmed, completed, closed, cancelled)`. `lost` is archived. Soft calendar holds are `possible` and `quoted`. "Waiting on" is never a column: it is computed from the booking, its agreements and invoices and the enabled features (business logic section 6). Converting is a stage change and copies nothing. `last_touched_at` is what the clash warning shows ("last touched three months ago"). The main event's date is not denormalised onto the booking; the list query joins the `main` event, which the partial index in 5.9 makes cheap. `pricing_mode` and `discount_type` are backed by `PricingMode` and `DiscountType` enums, which the prompt's enum list omitted and the migration added.

### 5.9 `events`

Anything that touches a date. Normally two rows per booking, `trial` and `main`.

```
id                   bigint   pk
account_id           bigint   not null fk accounts cascade
booking_id           bigint   not null fk bookings cascade
type                 varchar(20) not null
                       check: main | trial | consultation | shoot | setup |
                              delivery | collection | other
label                varchar(60)  nullable       custom display name, otherwise the locale string for the type
event_date           date     not null            local wall clock
start_time           time     nullable            the call time
end_time             time     nullable
ready_by_time        time     nullable
timezone             varchar(64) not null default 'Europe/London'   IANA
location_type        varchar(10) nullable         check: base | client | venue
address_line_1       varchar(120) nullable         where the work happens; travel is computed to this
address_line_2       varchar(120) nullable
city                 varchar(80)  nullable
postcode             varchar(12)  nullable
country              char(2)      nullable
latitude             numeric(9,6) nullable
longitude            numeric(9,6) nullable
venue_name           varchar(120) nullable         the occasion's venue when it differs from the address above
venue_address        text         nullable
travel_distance_m    int      nullable             unused in v1, decision 74
travel_duration_s    int      nullable
travel_estimated_at  ts       nullable
sort_order           smallint not null default 0
created_at, updated_at
index (account_id, event_date)
index (account_id, type, event_date)
index (booking_id)
partial unique (booking_id) where type = 'main'
```

Notes. The calendar, the clash warning and "next up" all query this table by `(account_id, event_date)`, which is the index decision 44 intended and could not put on `bookings`. At most one `main` event per booking. `location_type = 'base'` means the artist's own premises, and the address columns may then be empty because the base address lives in settings. `timezone` defaults to the account's zone; when an event's address country differs from the account's country the app sets the zone from the address and tells the artist, so a job abroad is never left on `Europe/London`. The clash warning compares `event_date` values, not instants, so a UK job and a Polish job on the same day still warn each other.

### 5.10 `party_members`

Who is being served, each with a service from the rate card. No ages, no dates of birth.

```
id              bigint   pk
account_id      bigint   not null fk accounts cascade
booking_id      bigint   not null fk bookings cascade
event_id        bigint   nullable fk events set null     which event, null means the main one
name            varchar(120) nullable
service_id      bigint   nullable fk services set null
service_name    varchar(80)  nullable                    snapshot, survives a deleted service
sort_order      smallint not null default 0
created_at, updated_at
index (booking_id)
```

### 5.11 `booking_contacts`

Everyone else on the day who is not the contact: partner, planner, venue coordinator, emergency contact.

```
id              bigint   pk
account_id      bigint   not null fk accounts cascade
booking_id      bigint   not null fk bookings cascade
role            varchar(30) not null      check: partner | planner | venue_coordinator | emergency | other
name            varchar(120) not null
email           varchar(255) nullable
phone           varchar(30)  nullable
note            varchar(255) nullable
created_at, updated_at
index (booking_id)
```

### 5.12 `services`

The rate card. Rows, never an enum. Seeded per vertical, all editable, all deletable.

```
id              bigint   pk
account_id      bigint   not null fk accounts cascade
name            varchar(80)  not null
description     varchar(255) nullable       the plain-English definition, e.g. "under 16"
kind            varchar(10)  not null default 'service'   check: service | expense | travel
applies_to      varchar(10)  not null default 'both'      check: main | trial | both
price_minor     bigint   not null default 0
sort_order      smallint not null default 0
active          bool     not null default true
created_at, updated_at, deleted_at
partial unique (account_id, name) where deleted_at is null
```

Notes. The wedding-makeup seed is: Bride, Bridesmaid, Mother of the bride, Senior, Child, Gentleman, Bridal trial, Additional trial, Early start supplement, Travel, Accommodation, Parking, Congestion or clean air charge, Other expense. Those are data and the artist can rename every one of them (decision 73). Whether a trial is included in a price is expressed by a zero price on that row; a dedicated toggle is deferred.

### 5.13 `booking_lines`

The live, editable price on the booking. Description and unit price are snapshotted the moment a line is added (business logic 4.6), with a visible "update to current prices" action.

```
id                bigint   pk
account_id        bigint   not null fk accounts cascade
booking_id        bigint   not null fk bookings cascade
service_id        bigint   nullable fk services set null
kind              varchar(10) not null default 'service'   check: service | expense | travel | custom
description       varchar(120) not null                    snapshot
quantity          smallint not null default 1
unit_price_minor  bigint   not null
sort_order        smallint not null default 0
created_at, updated_at
index (booking_id)
```

Notes. Line total is `quantity * unit_price_minor`, computed, never stored. Booking subtotal, discount and total are computed in one place in PHP and never stored on the booking. In fixed-price mode the lines are kept but ignored for totals, so switching back loses nothing.

### 5.14 `quotes`

An immutable record of a written quote that was copied, sent or downloaded, with an outcome. This is what makes the enquiry funnel measurable (business logic section 28).

```
id              bigint   pk
account_id      bigint   not null fk accounts cascade
booking_id      bigint   not null fk bookings cascade
number          smallint not null                 1, 2, 3 per booking
currency        char(3)  not null
pricing_mode    varchar(10) not null              itemised | fixed
lines           jsonb    not null                 snapshot of booking_lines at that moment
subtotal_minor  bigint   not null
discount_minor  bigint   not null default 0
total_minor     bigint   not null
deposit_minor   bigint   nullable
rendered_text   text     not null                 exactly what was copied or sent
status          varchar(10) not null default 'sent'   check: sent | accepted | declined | expired
sent_at         ts       not null
sent_via        varchar(10) not null default 'copy'    check: copy | email | pdf
responded_at    ts       nullable
valid_until     date     nullable
created_by_user_id bigint nullable fk users set null
created_at, updated_at
unique (booking_id, number)
index (account_id, status)
```

Notes. Copying to the clipboard creates a quote row, because that is the moment a number reached the client. Editing lines afterwards does not touch this row; the next copy creates quote 2. `pricing_mode` carries the same check constraint as on `bookings`.

### 5.15 `invoices`

One per booking by default, numbered at issue, carrying the deposit and both due dates. A second can be raised manually.

```
id                        bigint   pk
account_id                bigint   not null fk accounts cascade
booking_id                bigint   not null fk bookings cascade
status                    varchar(10) not null default 'draft'   check: draft | issued | void
sequence                  int      nullable                       assigned at issue from account_settings
number                    varchar(24) nullable                    prefix plus zero-padded sequence, e.g. INV-0042
currency                  char(3)  not null
issued_on                 date     nullable
lines                     jsonb    not null                       snapshot at issue
subtotal_minor            bigint   not null
discount_minor            bigint   not null default 0
total_minor               bigint   not null
deposit_minor             bigint   not null default 0             the part due first; 0 means no deposit stage
deposit_due_on            date     nullable
balance_due_on            date     nullable
payment_instructions      text     nullable                       snapshot from settings at issue
notes                     text     nullable                       shown on the invoice
reminders_snoozed_until   date     nullable                       decision 27, separate from recording a payment
pdf_path                  varchar(255) nullable
voided_at                 ts       nullable
void_reason               varchar(200) nullable
created_by_user_id        bigint   nullable fk users set null
created_at, updated_at
unique (account_id, sequence)
unique (account_id, number)
index (account_id, status, balance_due_on)
index (account_id, status, deposit_due_on)
index (booking_id)
```

Notes. Numbering happens inside a transaction that locks the `account_settings` row (`select ... for update`), reads `next_invoice_number`, writes it to `sequence`, and increments. Drafts have no number, so there are no gaps (business logic 11.1). Paid state is never stored: see section 8. A voided invoice keeps its number.

A draft's money columns and `lines` are zero and empty until issue; the snapshot is taken once, at issue, by `InvoiceNumbering::issue()`. A screen showing a draft shows the booking's live figures from `BookingPricing` instead, and the two are the same number until the moment of issue, after which the invoice is the record and the booking may move on (decision 80). `deposit_due_on` defaults to `issued_on` plus `deposit_due_days` and `balance_due_on` to the main event date minus `balance_due_days_before`, both overridable at issue.

### 5.16 `payments`

Rows, never a boolean. Negative amounts are refunds.

```
id                    bigint   pk
account_id            bigint   not null fk accounts cascade
booking_id            bigint   not null fk bookings cascade
invoice_id            bigint   not null fk invoices cascade
amount_minor          bigint   not null           negative for a refund; check amount_minor <> 0
paid_on               date     not null
method                varchar(20) not null default 'bank_transfer'
                        check: bank_transfer | cash | card | stripe | other
reference             varchar(80)  nullable
note                  varchar(255) nullable       required by the interface when amount is negative
external_id           varchar(255) nullable       Stripe payment intent, later
recorded_by_user_id   bigint   nullable fk users set null
created_at, updated_at
index (invoice_id)
index (account_id, paid_on)
```

Notes. `booking_id` is redundant with `invoice_id` and deliberate: "what has this booking paid" is the commonest money query and should not join. Hard delete on misclick.

### 5.17 `notes`

The dated stream. One row per note, newest first, private, never merged into a template.

```
id              bigint   pk
account_id      bigint   not null fk accounts cascade
booking_id      bigint   nullable fk bookings cascade
contact_id      bigint   nullable fk contacts cascade
user_id         bigint   nullable fk users set null    author
body            text     not null
remind_at       ts       nullable                      UTC instant, unused in v1
reminded_at     ts       nullable
created_at, updated_at
check (booking_id is not null or contact_id is not null)
index (booking_id, created_at)
partial index (account_id, remind_at) where remind_at is not null
```

### 5.18 `message_templates`

One table, three tiers. `account_id` null is a system default; `account_id` set is the artist's override; `booking_id` set is a per-booking override. Resolution is booking, then account, then system.

```
id              bigint   pk
account_id      bigint   nullable fk accounts cascade
booking_id      bigint   nullable fk bookings cascade
key             varchar(40) not null
                  enquiry_acknowledgement | introduction | introduction_follow_up | quote |
                  details_form_request | details_form_reminder | agreement_to_sign |
                  agreement_reminder | booking_confirmed | invoice_deposit_request |
                  deposit_reminder | trial_reminder | trial_follow_up | balance_due |
                  main_event_reminder | thank_you | feedback_request | gallery_request
locale          varchar(10) not null default 'en-GB'
vertical        varchar(40) nullable                    system rows only
name            varchar(80)  not null
subject         varchar(200) not null
body            text         not null                   merge fields in {{double_braces}}
variants        jsonb        nullable                   location blocks: base | client | venue
enabled         bool     not null default true
mode            varchar(10) not null default 'copy'    check: copy | send | automate
trigger         jsonb    nullable                       {event_type, offset_days, at_time}; unused in v1
sort_order      smallint not null default 0
created_at, updated_at
unique nulls not distinct (account_id, booking_id, key, locale)
check (booking_id is null or account_id is not null)
```

Notes. The keys are held in a PHP enum; the list above is the full menu from business logic 15.4 and the first build seeds four or five of them. `mode` and `trigger` are the automation columns, structured now and unread in v1 (decision 75). Merge fields are rendered against a booking by one service so that copy, preview and, later, send all produce the same text.

### 5.19 `contract_templates`

Versioned wording, by market and vertical. `account_id` null is the system default.

```
id              bigint   pk
account_id      bigint   nullable fk accounts cascade
market          char(2)  not null              the country whose law the wording assumes
vertical        varchar(40) not null
version         int      not null
name            varchar(80) not null
body            text     not null              plain text with merge fields, never rich text
effective_from  date     not null
retired_at      date     nullable
created_at, updated_at
unique nulls not distinct (account_id, market, vertical, version)
```

Notes. Not a file in the repository, because a signed agreement must be able to reference the exact row (technical proposal 15.7). The GB wedding-makeup system default carries the cancellation clause on by default per decision 23, pending the solicitor. Editing an account's template creates a new version row; the previous one is retired, not changed.

### 5.20 `agreements`

One row per version per booking. A signed agreement is never edited. In the first build an agreement is generated, downloaded as a PDF and marked as signed by the artist; the signing link arrives later and writes the same columns.

```
id                       bigint   pk
account_id               bigint   not null fk accounts cascade
booking_id               bigint   not null fk bookings cascade
contract_template_id     bigint   nullable fk contract_templates set null
version                  smallint not null                 1, 2, 3 per booking
status                   varchar(12) not null default 'draft'
                           check: draft | sent | signed | superseded | void
rendered_body            text     not null                 the exact text, not template plus data
rendered_sha256          char(64) not null
pdf_path                 varchar(255) nullable
total_minor              bigint   not null                 snapshot, for the record
deposit_minor            bigint   not null default 0
sent_at                  ts       nullable
first_viewed_at          ts       nullable                 analytics, never evidence
signed_at                ts       nullable
signed_method            varchar(10) nullable              check: link | manual
signed_name              varchar(120) nullable
signed_ip                inet     nullable
signed_user_agent        text     nullable
signed_note              varchar(255) nullable             manual: "signed copy received by email"
superseded_by_id         bigint   nullable fk agreements set null
created_by_user_id       bigint   nullable fk users set null
created_at, updated_at
unique (booking_id, version)
index (account_id, status)
```

Notes. Tokens are not on this table. When the signing link is built they live in `client_links` (section 7.2), so that intake forms, invoices and feedback use the same mechanism. "The agreement in force" is the highest-version row with `status = 'signed'`. A booking is `confirmed` when such a row exists and the deposit is covered (business logic 4.4).

### 5.21 `entitlements`

Who is allowed in, separately from who paid. Populated from Cashier's webhooks. Read only through `hasActiveEntitlement()`.

```
id                  bigint   pk
account_id          bigint   not null fk accounts cascade
source              varchar(10) not null      check: stripe | apple | google | manual
external_id         varchar(255) nullable     subscription id
plan_key            varchar(40)  nullable     early_access_monthly, early_access_annual, ...
status              varchar(12) not null      check: trialing | active | past_due | paused | cancelled | expired
current_period_end  ts       nullable
created_at, updated_at
index (account_id, status)
```

Notes. `manual` exists so that Jess, the App Store review account and any comped artist can be let in without a Stripe object. Pause (business logic 26.3) is `status = 'paused'`: read-only app, automation stopped, data untouched.

### 5.22 `booking_user`

Which collaborators are on which booking. Empty in the first build; the shape is settled so it is created now.

```
id              bigint   pk
account_id      bigint   not null fk accounts cascade
booking_id      bigint   not null fk bookings cascade
user_id         bigint   not null fk users cascade
added_by_user_id bigint  nullable fk users set null
created_at, updated_at
unique (booking_id, user_id)
```

Notes. Permissions are on `account_user`, not here. This table only answers "is this person on this job".

---

## 6. Framework tables

Created by the packages, listed so nothing is a surprise.

| Table | From | Note |
|---|---|---|
| `subscriptions`, `subscription_items` | Cashier 16 | Not yet published. Arrive with the billing prompt, pointed at `accounts` (`account_id`, not `user_id`) |
| `personal_access_tokens` | Sanctum | Migrated. One per device, named, with `expires_at` set and `sanctum:prune-expired` scheduled from Prompt 4 |
| `users.two_factor_*` columns | Fortify | Migrated. `two_factor_confirmed_at` is `timestamp`, not `timestamptz`, because it is the package's migration |
| `passkeys` | Fortify | Migrated, empty until passkeys are switched on. Uses `json`, not `jsonb`, for the same reason |
| `sessions` | Laravel | Web sessions for the cookie path |
| `password_reset_tokens` | Laravel | |
| `cache`, `cache_locks` | Laravel | |
| `jobs`, `job_batches`, `failed_jobs` | Laravel | On Laravel Cloud the managed queue replaces `jobs`; the migration is harmless |

---

## 7. Designed, not migrated

Each of these is a new table pointing at an existing one, so adding it later is the cheap kind of change (decision 75). They are here so the first migration set leaves the right hooks, and so nobody designs them twice.

### 7.1 `scheduled_messages` (with messaging automation)

```
id, account_id, booking_id, event_id nullable, template_key, template_id nullable,
to_email, to_name, subject, body (rendered at materialisation), send_at ts (UTC),
status: pending | sent | cancelled | failed, hand_edited bool, sent_at ts,
provider_message_id, delivery_status: queued | delivered | bounced | complained,
delivery_updated_at, error text, created_at, updated_at
index (account_id, status, send_at), index (booking_id)
```

`send_at` is computed from the event's local time and zone into UTC, and recomputed when the event moves. "Regenerate from template" re-renders rows where `hand_edited` is false.

### 7.2 `client_links` (with the first client page)

One table for every tokenised URL the client receives.

```
id, account_id, booking_id, purpose: agreement_sign | agreement_receipt | intake_form |
invoice_view | feedback | enquiry_form, subject_type, subject_id, token varchar(64) unique
(UUIDv4 or 32 random bytes, hex), expires_at ts nullable, revoked_at ts nullable,
first_viewed_at ts, view_count int, pin_failures int, pin_locked_until ts,
created_at, updated_at
index (token), index (booking_id, purpose)
```

GET renders, POST acts, tokens are multi-use within their window, `410 Gone` when dead (decision 22). The PIN from decision 72 is checked here before rendering anything.

### 7.3 `signing_events` (with signing by link)

Hash-chained, append-only, never pruned, retained six years past the main event (business logic 26.4).

```
id, account_id, agreement_id, event: sent | delivered | viewed | pin_passed |
consented | signed | receipt_viewed, occurred_at ts, ip inet, user_agent text,
payload jsonb, previous_hash char(64) nullable, hash char(64)
index (agreement_id, id)
```

No `updated_at`. Separate from the artist's activity trail (7.9) because this one can never be edited and that one can.

### 7.4 `intake_forms` and `intake_questions` (with the intake form)

```
intake_forms: id, account_id, booking_id, status: draft | sent | returned | reviewed |
taken_by_phone, sections_enabled jsonb, answers jsonb, submitted_answers jsonb
(what the client sent, before review), sent_at, returned_at, reviewed_at,
created_at, updated_at, unique (booking_id)

intake_questions: id, account_id, label, type: short_text | long_text | yes_no | choice,
options jsonb, sort_order, active, created_at, updated_at
```

Five custom questions maximum, enforced in the application. Never a form builder.

### 7.5 `feedback_responses` (with private feedback)

```
id, account_id, booking_id, client_link_id nullable, is_primary_contact bool
(the contact's own link, distinguishable per business logic 12.1), ratings jsonb,
comment text, submitted_at ts, created_at
```

### 7.6 `media` (when object storage is ruled back in)

```
id, account_id, booking_id, label: inspiration | trial | main, disk, path,
original_name, mime, size_bytes, width, height, uploaded_by_user_id nullable,
created_at, updated_at
```

Consent is read from `bookings.photo_consent`, not stored here.

### 7.7 `push_tokens` (with push)

```
id, user_id, platform: ios | android, token varchar(255) unique, app_version,
last_seen_at ts, created_at, updated_at
```

Person-level, not account-level: a user in two accounts has one phone.

### 7.8 `activities` (with the booking activity trail)

```
id, account_id, booking_id nullable, contact_id nullable, user_id nullable,
event varchar(40), subject_type, subject_id, changes jsonb, created_at ts
index (booking_id, created_at)
```

The artist's "what did I do and when". Prunable. The events are named for the job (`stage_changed`, `payment_recorded`, `agreement_signed`), never the industry.

### 7.9 `booking_transfers` (with transfer)

```
id, from_account_id, to_account_id, booking_id, initiated_by_user_id,
to_email, status: pending | accepted | declined | cancelled, accepted_at,
keep_read_access bool, created_at, updated_at
```

Transfer changes `bookings.account_id` in one transaction and rewrites `account_id` on every child row, which is the one operation the redundancy in section 2 makes expensive and which is rare enough to accept.

### 7.10 Per-vertical extension tables (when a vertical needs one)

`booking_<vertical>` keyed to `booking_id`, named for the data it holds. None designed. Decision 73.

---

## 8. Values that are computed, never stored

Storing any of these creates a second source of truth that drifts.

| Value | Computed from |
|---|---|
| Line total | `quantity * unit_price_minor` |
| Booking subtotal, discount, total | `booking_lines` plus `pricing_mode`, `fixed_price_minor`, `discount_*`, in one PHP service |
| Deposit amount for a booking | account rule, overridden by the booking's `deposit_override_*` |
| Invoice paid, deposit received, balance outstanding | `sum(payments.amount_minor)` against `deposit_minor` and `total_minor` |
| Reminders due | outstanding balance past `balance_due_on` or `deposit_due_on`, unless `reminders_snoozed_until` is in the future, unless invoicing is toggled off. `Invoice` now says the halves separately, because a figure split on the conflated question reported a snoozed late balance as not yet late: `isPastDue()` is the date, `isSnoozed()` is the pause, `isOverdue()` is both, `balanceIsPastDue()` is the half the owed figure is about. Decision `2026-09-06.2210` |
| Waiting on | stage, latest agreement status, invoice state, `hold_expires_at`, `last_touched_at`, and the enabled features, per business logic section 6 |
| Calendar mark strength | `confirmed` solid, `provisional` outlined, `possible` or `quoted` a count |
| Agreement in force | highest-version `agreements` row with `status = 'signed'` |
| Owed to you | the sum of the `client_balance` attention rows: per booking, every invoice whose balance is past `balance_due_on` and not snoozed, filtered to the account currency. **Not** past-date `main` events, which is what this row said until 6 September 2026 and is a different set: a wedding last month whose balance is not due until next month is in that definition and not in this one. Decisions `2026-09-06.1954` and `2026-09-06.2114` |
| Money tiles | always grouped by `currency` or filtered to the account currency, never a bare `SUM` |

---

## 9. Indexes and the queries they serve

| Query | Table and index |
|---|---|
| Calendar month, clash warning, next up | `events (account_id, event_date)` |
| Bookings list sorted by main event date | `events (account_id, type, event_date)` plus the `main` partial unique |
| Enquiries list, bookings list | `bookings (account_id, stage)` |
| Cold enquiries, "last touched" in the warning | `bookings (account_id, last_touched_at)` |
| Overdue balances, home money figure | `invoices (account_id, status, balance_due_on)` |
| Overdue deposits | `invoices (account_id, status, deposit_due_on)` |
| What has this booking paid | `payments (invoice_id)`, `payments.booking_id` |
| Contact search | `contacts (account_id, last_name, first_name)`, `contacts (account_id, email)` |
| Login | `users` unique on `lower(email)` |
| Provider sign-in | `identities (provider, provider_user_id)` |
| Hostname to account | `accounts.username` unique, then `username_history.username` unique for redirects |
| Note reminders due | partial `notes (account_id, remind_at)` |

Every composite index leads with `account_id`. None leads with `user_id`.

---

## 10. Laravel mechanics

Collected here so the tables above stay readable.

- `$table->id()` produces `bigserial` on Postgres. Acceptable; identity columns are not worth a raw statement.
- Laravel has no check-constraint API. Each one is a `DB::statement('alter table ... add constraint ... check (...)')` in the same migration, named `<table>_<column>_check` so it can be dropped when widened.
- `unique nulls not distinct` (Postgres 15 and later) is also a raw statement. It is what lets `message_templates` and `contract_templates` hold system rows with a null `account_id` and still be unique.
- The case-insensitive unique on `users.email` is `create unique index users_email_lower_unique on users (lower(email))`, again raw.
- `timestampsTz()` for `created_at` and `updated_at`; `timestampTz()` for every other instant; `date` and `time` for wall clock.
- `jsonb()`, never `json()`.
- Money columns use a `Money` cast that exposes the minor-unit integer as a value object carrying its currency, never a float. Formatting is currency plus locale, never hard-coded symbols.
- `access_pin` uses the `encrypted` cast.
- Every model except `User`, `Account`, `Identity` and `UsernameHistory` uses a `BelongsToAccount` trait that applies the global scope and fills `account_id` on create from the current account context (`App\Support\CurrentAccount`, a container singleton).
- With no current account bound, a scoped query returns nothing and creating throws. A forgotten binding therefore leaks nothing rather than everything. Code that must work across accounts, such as the seeders and the hostname lookup, says `withoutGlobalScope('account')` explicitly.
- `MessageTemplate` and `ContractTemplate` override the scope to show the account's own rows plus the system rows with a null `account_id`, which a plain `account_id = ?` scope would hide.
- `MoneyCast` takes the currency from the model's own `currency` column, then from its booking, then from the account. `services` and `account_settings` have neither of the first two, so they fall back to the account's currency.
- `Money::format()` passes one float to ICU's `formatCurrency`, because that is the only signature it has. No arithmetic happens in floating point and the value is already rounded, so the digits cannot change. The class says so in a comment.
- Cashier's customer model is set in `AppServiceProvider` with `Cashier::useCustomerModel(Account::class)`. Without it the package assumes `User`, which has no Stripe columns.
- The Pest suite runs against a real Postgres database, `klaroly_test`, because the check constraints and partial indexes are part of what is tested. SQLite is deliberately not used.
- Cashier: `Cashier::useCustomerModel(Account::class)`, and the published Cashier migrations altered to add the four columns to `accounts` and to key `subscriptions` on `account_id`.
- Enum-like columns are backed by PHP enums under `App\Enums`, and the check constraint's value list is generated from the enum in the migration so the two cannot drift.

---

## 11. What changes from Prompt 3

For the rewrite, so nothing is lost in translation.

| Prompt 3 | This document |
|---|---|
| Separate `enquiries` table | Gone. A stage on `bookings` |
| `clients` | `contacts`, with `user_id` |
| Venue, coordinates, travel on `bookings` | On `events`, with `location_type`, `venue_name`, `venue_address` |
| `booking_services` | `booking_lines`, with `kind` and `sort_order` |
| Stage values `possibility`, `won` | The ten values in 5.8 |
| `photographer_gallery_url` | `gallery_url` |
| Event type `wedding` | `main`; list per decision 73 |
| `attachments` | Not migrated; `media` designed in 7.6 |
| One hash-chained `audit_events` | `activities` (7.8) and `signing_events` (7.3), both deferred |
| Tokens on `agreements` | On `client_links` (7.2), deferred |
| Reserved usernames as a table | A config file; `username_history` remains a table |
| `account_user.role` owner, artist, assistant | owner, collaborator, with `can_see_contacts` added |
| Missing | `account_settings`, `party_members`, `booking_contacts`, `quotes`, `message_templates`, PIN columns, consent columns, `features` and `feature_overrides`, `marketing_consent_*`, `last_touched_at`, `source_booking_id`, `hold_expires_at` |

---

## 12. Confirmed before the first migration ran

All five were taken as recommended on 2 September 2026: the six calls in section 3; `account_settings` as a separate table; `deposit_percent` as a whole number; six seeded `message_templates` keys (`enquiry_acknowledgement`, `quote`, `booking_confirmed`, `invoice_deposit_request`, `main_event_reminder`, `thank_you`); and the wedding-makeup rate card seed in 5.12, to be shown to Jess before it is called final.

---

## 13. What the first migration set found

Prompt 3 ended by asking Claude Code to list every place this document was ambiguous, wrong, or would bite later. This section records each point and how it was settled, so the answer is not lost in a chat. The tables above already reflect these.

| Finding | Settled as | Where |
|---|---|---|
| Header said 21 tables; section 5 numbers 22 | 22. Header corrected | Section 5 |
| `PricingMode` and `DiscountType` were missing from the prompt's enum list though the columns are constrained | Both enums added; the `pricing_mode` check is on `quotes` as well as `bookings` | 5.8, 5.14 |
| Feature keys were defined nowhere in the repository; Claude Code invented eight and made an unset key mean on | Decision 74's nine keys; an unset key is off; registration writes the default map from `config/features.php`. Prompt 4 renames the enum | 5.2, decision 78 |
| Reads with no current account: the prompt only said creating must throw | Scoped queries return nothing; cross-account work says `withoutGlobalScope('account')` | Section 10 |
| System template rows would be hidden by a plain `account_id = ?` scope | `MessageTemplate` and `ContractTemplate` show their own rows plus system rows | Section 10 |
| `MoneyCast` currency for `services` and `account_settings`, which have no currency column and no booking | Falls back to the account's currency | Section 10 |
| `Money::format()` cannot avoid one float, because ICU's `formatCurrency` takes nothing else | Accepted; no arithmetic in floating point; commented in the class | Section 10 |
| `deposit_due_days` said "days after signature" but issuing an invoice has no signature date | Counts from the invoice issue date | 5.2, 5.15, decision 79 |
| Draft invoices carry zeros until issue | By design; a draft displays the booking's live `BookingPricing` figures | 5.15, decision 80 |
| `travel_rate_per_mile_minor` was `int` against the bigint rule | `bigint` | 5.2 |
| `marketing_consent_source` listed values but had no check | Check constraint and enum added in Prompt 4 | 5.3 |
| Fortify's `two_factor_confirmed_at` is `timestamp` and the passkeys table uses `json` | Package migrations, left as they are | Section 6 |
| Decision 73 bans `trial` as a name, yet it appears as an enum value | The rule is about names, not values | Section 2, decision 81 |
| Cashier's customer model | `Cashier::useCustomerModel(Account::class)` in `AppServiceProvider` | Section 10 |
| `users.email` is not lowercased on write, and Fortify's lookup is case-sensitive | Authentication normalises email on the way in; the index is the backstop | 5.3, Prompt 4 |
| Postgres is 18 locally, not 17 | Documents corrected; no effect on the schema | Status line, README |
| Agreements seeded as signed on completed and closed bookings too, contacts reused across bookings, real venue names with postcodes believed correct | Accepted as demo data; spot-check the postcodes before the App Store review account goes live | Seeder |
| Tests need a `klaroly_test` database | Created once with `createdb klaroly_test`; documented in README and CLAUDE.md | Section 10 |

---

## 14. What the authentication prompt found

Prompt 4 ended the same way as Prompt 3. Each point, how it was settled, and where the answer lives.

| Finding | Settled as | Where |
|---|---|---|
| `account_settings` defaults violate the table's own check: `deposit_type` defaults to `percent` but `deposit_percent` had no default, so a bare insert failed | `deposit_percent` defaults to 25; the migration rides in Prompt 5 and registration stops writing the value | 5.2, decision 90 |
| The demo seeder read `env()` directly for the demo password | Reads `demo.password` from `config/demo.php` | Section 10 rule: nothing outside `config/` reads `env()` |
| Fortify's register, forgot-password, reset-password and resend-verification routes need the CSRF cookie, and profile and password update run under `auth:web`, so a bearer-token caller cannot use them; a Capacitor WebView may not hold the API's session cookie at all | JSON twins under `/api/auth` in Prompt 5; profile and password twins with the settings screen | Decision 87 |
| Two-factor, passkey and confirm-password routes answer requests because the features stay configured | Accepted; configured, unused, nothing links to them | Decision 84 |
| The verification link loses the intended URL when the browser is logged out, and on a phone opens a browser with no session | Accepted under decision 83; revisit when enforcement arrives | Decision 92 |
| A soft-deleted user's email cannot be reused at registration | Deferred to the account deletion prompt | 5.3, decision 91 |
| The token endpoint refuses a user with no membership with the same 403 as the middleware and issues no token | Accepted; it is decision 84's rule applied one step earlier | Decision 84 |
| Fortify's password broker looks the user up by plain equality on `email`, so it does not use the functional index | Harmless because every stored value is lowercase; the index is the backstop | 5.3 |
| The test client keeps the Sanctum guard and the tenant singleton across requests within one test, which real requests do not | The tenancy test resets both and says why | `tests/Feature/Auth` |

---

## 15. What the mobile authentication routes found

Prompt 5 ended the same way. Nothing here changes a table; two points are about the gap between a model and its row, and the rest are recorded so the app prompts do not rediscover them.

| Finding | Settled as | Where |
|---|---|---|
| No test asserted the 25 percent default the prompt said must still pass | The registration test asserts it, and a bare `account_settings` insert is tested in the constraints test | 5.2, decision 90 |
| A model returned by a create action does not carry the database defaults: `notification_preferences` was null on the fresh user while the row held `{}`, so the register twin's `me` differed from `GET /api/me` | The controller refreshes the user before building the payload; any response that returns a user straight after a write must read it back first | 5.3, decision 95 |
| Fortify's response classes redirect when the request does not send `Accept: application/json`, so the reset and verification twins would redirect too | Accepted; the app sends the header on every request, and Prompt 6 tests that it does | Decision 96 |
| The register twin validates `device_name` before the registration fields, so a client that omits it sees that error alone | Accepted; the app always sets `device_name` itself | Decision 96 |
| The register limiter is keyed on IP alone, five a minute, which a shared network could hit | Accepted for launch; the first limit to loosen | Decision 93 |
| The reset-password twin has no limiter, matching Fortify's route | Accepted; tokens expire and the broker's check is constant-time. A limiter on email plus IP is cheap when wanted | Decision 94 |
| The reset link in the email points at the web app, so a phone opens it in a browser, and a reset on mobile issues no token | Accepted; the screen sends the person to the login screen. Deep links are a Capacitor decision | Decision 92, Prompt 6 |


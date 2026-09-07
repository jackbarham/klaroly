# The enquiries endpoint

`GET /api/enquiries` is what the enquiries screen reads.
`App\Http\Controllers\EnquiryController` serves it.

- **There is no enquiries table and there never will be.** Business logic 4.3
  is one bookings table with a stage column and every other field nullable, and
  the interface shows enquiries and bookings as two lists filtered on stage.
  This route returns bookings, and calling it `/enquiries` is the same
  two-views-of-one-table framing rather than a second model.
- **The boundary is provisional, not confirmed** (decision 235). Enquiries are
  `new`, `in_conversation`, `possible` and `quoted`, plus `lost`, which is
  archived and comes back so the screen can show it behind a switch. Everything
  from `provisional` onwards is the bookings list. Converting is the artist's
  own tap: it moves the record to provisional there and then, and it is
  reversible until something is signed. Nothing in the system ever promotes an
  enquiry on its own, and a deposit arriving cannot, because a deposit cannot
  arrive against a record with no invoice. Signing and depositing turn
  provisional into confirmed, and what that changes is the calendar mark, not
  which list the record is in. The stage set is `Booking::ENQUIRY_STAGES` plus
  `Lost`, so a fifth live stage is one edit rather than two.
- **One row per enquiry, not one per event** (decision 234), and this is the one
  place the endpoint must not copy `GET /api/events`. That one returns a row per
  event because the calendar's unit is a day; here the unit is the
  conversation, for two reasons that are both ordinary rather than edge cases.
  An enquiry often has no date at all, and "next summer, we have not booked the
  venue yet" is one of the most winnable kinds there is, which an events-shaped
  payload cannot represent because there is no row. And an enquiry with a trial
  and a wedding is still one conversation, where two rows would mean two
  staleness figures reading the same number and two chances to reply twice to
  the same person.
- **No parameters, no pagination, no filter and deliberately no `stage`.** Same
  design as contacts: the screen holds the whole list and sorts, groups and
  filters it in the browser. A stage parameter in particular would be a second
  way of saying what the stage set already says, and the screen's groups are
  the waiting-on axis and the staleness bands rather than the stage.
- **Staleness is the order, and New is pinned above it** (decision 236).
  `last_touched_at` ascending, so the top of the list is the thing nobody has
  touched for longest, which is what the screen is for. But an enquiry at `new`
  has the freshest timestamp in the list and would sort to the bottom, which is
  exactly backwards, because it is the one nobody has looked at. So `new` sorts
  above everything, newest first, `lost` sorts last however it is ordered among
  itself, and the tie-break is `id`. The screen re-sorts anyway; this exists for
  the truncation rule and for a total, stable order, exactly as the contacts
  ordering does.
- **The ceiling is a flag, not a 422**, at `bookings.max_enquiries`, with
  `total`, `returned` and `truncated` in the meta block, for the reason contacts
  gives: a caller that sends no parameters cannot ask for less. The ordering is
  what makes it survivable, because `lost` sorts last and the archive is the
  unbounded half. The cap is in `config/bookings.php` rather than a
  `config/enquiries.php` of its own, which is the opposite call from contacts
  and for the reason that justified that one: it is about nouns. A contact is a
  different thing from a booking; an enquiry is the same noun at an earlier
  stage, and `cold_enquiry_days`, the number the screen turns on most, already
  lives in that file.
- **The row carries one `event`, not the enquiry's events**: the main day, or
  the earliest when there is no main one, chosen by
  `App\Services\ContactActivity::mainEvent()` so the enquiries row and the
  contacts card cannot show one booking under two different dates. It carries
  `location_type` for the reason `GET /api/events` already found, that the venue
  columns cannot tell "nobody has said" from "at her own place", and it does not
  carry `start_time`, because an enquiry rarely has a call time and the row does
  not show one. **The limitation that comes with one date: an enquiry with a
  trial in March and a wedding in May is checked for a clash on May only.** A
  trial-date clash is the calendar's job, and a test says so.
- **`waiting_on` comes from `App\Services\WaitingOnResolver` and nothing
  computes it twice.** This endpoint is why `enquiryCold()` was widened from
  Possible to every live enquiry stage; see the bookings-endpoints section
  above.
- **`clash` is `{confirmed, provisional, others}` or null**, per business logic
  5.2 and decision 2026-09-06.1804, where `others` counts other enquiries at
  `possible` or `quoted` on the same date. Null when the date carries nothing
  else, when the enquiry has no date, and when the enquiry is `lost`, because
  lost has released the date. Two things about it are decisions rather than
  details. **The counts describe what is ALREADY on the date**, so a row whose
  own stage holds nothing still gets them: an `in_conversation` enquiry carries
  no calendar mark and still reports the confirmed booking and the two possible
  enquiries sitting on its Saturday, which is a deliberate departure from the
  calendar's rule. And **the stage buckets are the calendar's own**, taken from
  `strengthByStage` in `app/src/lib/dayMarks.ts`: `confirmed`, `completed` and
  `closed` are filled, `provisional` is a ring, `possible` and `quoted` are the
  badge, and `new`, `in_conversation`, `lost` and `cancelled` are nothing.
  Reasoning it out again as "completed and closed are in the past" would be
  nearly always true and enforced by nothing, and the first booking marked
  completed with its date still ahead would have this list and the calendar
  describing the same Saturday differently, which is the exact failure the
  counts exist to prevent.
- **The clash is one query for every date in the payload, then matched in
  memory.** Counting per row is the obvious N+1 and it is the worse kind,
  because it grows with the number of distinct dates rather than with the number
  of rows, so it survives every test written against a handful of enquiries
  sharing one Saturday. `EnquiryIndexTest` holds the query count flat against
  both.
- **`source_booking` is an object, not an id beside a copy of itself.** It
  carries the id, the client's name and that booking's date, which is enough to
  say "met at Elspeth Rowntree's wedding", and its date is chosen by the same
  `mainEvent()` the row uses.
- **How an enquiry ended is a reason with a side, not a stage** (decision
  2026-09-06.1512). `App\Enums\LostReason` gives `bookings.lost_reason` its
  nine values and `side()` returns `App\Enums\EndingSide`. A tenth stage would
  have bought the same label and charged for it in `strengthByStage`, in
  `WaitingOnResolver`, in both list filters, in the stage check constraint and
  in every future test of whether a record is still live; the two endings behave
  identically, and the only thing that differs is who decided. The payload sends
  `lost_reason` as the key and `lost_side` beside it, because the side is a fact
  about the record and the label is wording, and facts come from the server.
  **The column has no check constraint yet**: the enum holds the line at the
  application boundary and the constraint goes in with the schema rewrite rather
  than as an ALTER migration of its own, generated from
  `LostReason::checkConstraintSql()`.
- **Nothing writes `where('account_id', ...)` by hand and nothing reaches for
  `DB::table()`.** The clash query in particular reads like a query-builder job,
  and written that way it counts every account's bookings while looking
  perfectly correct in a development database with one account in it. It is
  built from `Event::query()` so the global scope comes with it, and the
  soft-delete check on the joined `bookings` is written out because a join does
  not carry the joined model's scopes. There is a test that another account's
  booking on the same date is not counted.

- **`total_minor` is null when nobody has priced the enquiry**, and nought only
  when somebody has priced it at nothing. A total of nought and no price are
  different facts and the screen says so: "No price yet" against an enquiry
  nobody has quoted, which is most of them, and "£0" against a job somebody is
  doing for nothing. Neither the total nor the stage can separate them, since an
  enquiry at Possible can carry a price and one at Quoted can have had its lines
  deleted, so the predicate is `App\Services\BookingPricing::isPriced()` and it
  lives beside the sum it qualifies. A resource asking "are the lines empty"
  would be a second definition of priced. **`GET /api/events` and
  `GET /api/contacts` still send nought for both**, and adopting `isPriced()`
  there is a one-line change in each plus an edit to two front-end types, which
  is a change to their own contracts and belongs in their own prompts. That was
  still so on 7 September 2026: both resources call `BookingPricing::total()`
  unconditionally.
- **The currency is sent whether or not there is a price**, because it is a fact
  about the booking rather than about the price: a job in euros nobody has
  quoted is still a job in euros.

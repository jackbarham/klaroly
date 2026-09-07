# The bookings endpoints

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
  price, review, signature, form. Cold sits above price because the two
  collide at Possible, where an enquiry with no quote is both unpriced and,
  once it has sat long enough, cold: with price first the cold value could
  never be reported there, and Possible is where most enquiries sit.
  Suppression by feature is inside each branch, not a filter over the top, so
  with invoicing off the money checks never run rather than running and having
  their answers discarded.
- **Cold fires at every live enquiry stage, not only Possible.** It was
  narrowed to Possible when the home screen was the only consumer, and the
  enquiries endpoint widened it to `Booking::ENQUIRY_STAGES` through
  `isEnquiry()`: a quote sent three weeks ago with no reply and a conversation
  that has gone silent are both things the artist has not done, which is what
  the axis is for. It stops at the enquiry boundary, so a provisional booking
  left alone for a month is not cold, and a test asserts that as well as the
  widening. Resolving it on the server is what lets the enquiries screen's
  "Gone quiet" group be simply every row whose `waiting_on` is
  `artist_enquiry_cold`, with `bookings.cold_enquiry_days` read once and never
  reaching the client.
- **An archived booking waits on nobody.** `lost` and `cancelled` return null
  from a guard at the top of `for()`, before the precedence list runs, because
  nobody is going to act on either. It is a guard rather than a filter over the
  answer: this is where the question is answered, and a caller discarding an
  answer it did not want would be a second opinion held somewhere else. Without
  it a lost enquiry carrying an agreement that was sent and never signed
  reported `client_signature` on a row the artist had already closed.
- **Two of the eight values are unreachable on purpose.** `client_form` and
  `artist_review` both need `intake_forms`, which is schema section 7.4:
  designed, not migrated. The branches exist and return nothing, and a test
  asserts they are unreachable by design rather than by accident.
- **Eager load or this becomes the slowest thing in the app.** A test asserts
  the query count does not grow with the number of events. One eager load
  looks redundant and is not: `booking.lines.booking` is there because
  `booking_lines` has no currency column, so `MoneyCast` resolves a line's
  currency through its booking, and without it that is a query per line.

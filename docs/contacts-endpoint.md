# The contacts endpoint

`GET /api/contacts` is what the contacts screen reads.
`App\Http\Controllers\ContactController` serves it.

- **No parameters, no pagination and no filter, and that is the design.** The
  screen holds the whole list in memory and does its own sorting, grouping and
  filtering with no round trip, which is what makes the filter box instant and
  what makes the screen work with no signal. A page size or a search parameter
  would buy nothing and would take that away.
- **The ceiling is a flag, not a 422.** `config/contacts.php` caps the response
  at 1000 and the meta block carries `total`, `returned` and `truncated`. This
  is the opposite call from the events row cap, and deliberately: a caller that
  sends no parameters cannot ask for less, so refusing would leave the one
  account with five thousand contacts looking at a dead screen. Measured against
  the demo seeder a contact costs about 750 bytes uncompressed and 134
  compressed, so the cap is roughly 750KB on the wire before gzip and 130KB
  after.
- **Ordering is work ahead of you first and soonest first, then history newest
  first, then everybody with neither.** It is not the arbitrary "activity
  descending" it could have been: because a contact with a future date sorts
  above every contact without one, a truncated response is the useful end of the
  list rather than a slice, and the server's order matches the screen's default
  so a future consumer gets it free. Ties break on id, so two identical requests
  render identically.
- **The event a booking carries depends on why it is being shown**, and
  `App\Services\ContactActivity` is the one place that decides. `bookings[]`
  shows the main day, because a list of somebody's work is a list of the jobs
  and a trial is part of one of them; `next_booking` shows the soonest future
  event of **any type**, because that field answers when the artist next sees
  this person, so on 1 August a contact with a trial on the 15th reads "15 Aug,
  trial"; `last_booking` is the most recent past event of any type. A booking
  with no main day, a standalone trial or a shoot, falls back to its earliest
  event. All three render through one `ContactBookingResource` taking a
  `BookingOccasion`, so the three cannot drift.
- **`outstanding` is an array and nothing depends on its order.** One entry per
  currency, because schema section 8 forbids summing across them, and each entry
  carries `is_account_currency` so the client selects rather than trusting a
  position. "The account's currency is first" would be a correctness contract
  carried by array position with nothing asserting it, and it would break the
  first time somebody added an ORDER BY for an unrelated reason. Sorted by
  currency code anyway, so two identical requests render identically. Empty when
  nothing is owed: not null, and not a zero entry.
- **`App\Services\OutstandingBalances` groups, it does not calculate.**
  `App\Models\Invoice` already owns an invoice's balance in `outstandingMinor()`
  and `isOverdue()`, so re-deriving it here would be a second answer to a
  question that already has one, the same way summing booking lines would be a
  second answer to `BookingPricing`.
- **The ordering is the one rule written twice**, in SQL in the controller
  because ordering must happen before the limit, and in PHP in `ContactActivity`
  because that is what fills the fields. A test asserts the server's order is
  the order you get by sorting the payload's own `next_booking` and
  `last_booking`, which is what holds the two together.
- **Nothing indexes the sort key and the test says so.** It is a correlated
  subquery, so Postgres computes it per contact and sorts the results; what is
  indexed is the lookup inside it, `bookings (account_id, contact_id)` then
  `events (booking_id)`, both already in schema section 9. The plan test asserts
  those two by name **and** asserts the sort is a sort, so nobody reads it as a
  promise the ordering is cheap. It only means anything against realistic row
  counts: an earlier version passed while proving nothing, because with one
  booking in the table Postgres had sequentially scanned that too.

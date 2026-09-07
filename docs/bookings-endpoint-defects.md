# What the contacts work found in the bookings endpoint

Two defects, both fixed here rather than left for later, because both are about
the same question being answered twice.

- **`Invoice::paidMinor()` ignored an eager-loaded relation.** It ran its own
  `sum()` query every time it was asked, and `WaitingOnResolver` asks it two or
  three times per invoice through `outstandingMinor()` and `depositCovered()`.
  So `GET /api/events` had been paying a query per invoice since it was written,
  on a relation it had already paid to load. It now reads the loaded relation
  when there is one.
- **Fixing that moved the N+1 rather than removing it**, which is the same trap
  a third time: summing `Money` touches `MoneyCast`, `payments` has no currency
  column, so each payment resolved its currency through its booking.
  `EventController` now loads `booking.invoices.payments.booking` beside the
  `booking.lines.booking` that was already there for the identical reason.
  `EventIndexTest` holds the query count flat against the money on a booking,
  and **that test's first version passed against the unfixed model**: the
  resolver returns on the first invoice waiting on something, so with unpaid
  invoices it looked at one however many there were. It takes settled invoices
  past their due date to make both loops run to the end.
- **`isOverdue()` compared against a UTC day.** `APP_TIMEZONE` is UTC, so for
  the last hour of a British summer evening an invoice due today read as
  overdue while the artist was still on the day it was due. It now takes the day
  to judge against, and `WaitingOnResolver` was moved to the account's timezone
  in the same change. Fixing only one would have left the app with two answers
  to "is this overdue", and for that hour the bookings screen would have said no
  while the contacts screen said yes.

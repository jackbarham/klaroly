# The contacts screen

`/contacts` is a list beside one person's card, and it reads
`GET /api/contacts` through `src/lib/contacts.ts`, which is written the way
`src/lib/enquiries.ts` is: one `api.get`, unwrapping `data` and `meta`, below
the store and above `src/lib/api.ts`. A component reaching past the store to it
is a test failure rather than a convention, in `src/lib/boundary.test.ts` by
derivation and in `src/lib/contacts.guards.test.ts` by name.

It was built against a seam first. `src/lib/contactFixtures.ts` stood in for
the endpoint, and the swap deleted it. **Almost nothing else in the feature
changed, and the two things that did are the two the fixtures could not have
told anybody about**, which is the honest lesson of building against a seam:
the field names of `outstanding`, and a booking with no event. Both are below.

- **A contact is the person who books and pays, and that is all.** Per schema
  5.7 it holds a name, one email, ONE phone number and an address. Everyone
  else on the day is a party member or a booking contact and neither appears
  here. `src/types/contacts.ts` is the view model, in **snake_case** like
  `types/bookings.ts` and `types/auth.ts` before it, and it imports `EventType`
  and `BookingStage` rather than declaring them again: the wedding day is
  `main` and there is no `wedding` value here either.
- **`last_name` is nullable and the whole feature has to mean it.** Somebody
  with one name sorts under that name, takes one initial rather than two, and
  is never filed under a dash. A to Z puts everybody without a surname in one
  final Other group rather than sprinkling them through the letters under their
  first initial, because a list that files Anna under A next to Adebayo is a
  list where half the As are surnames and cannot be scanned.
- **`outstanding` is an array of
  `{currency, amount_minor, is_overdue, is_account_currency}`, not a figure and
  a flag.** Schema section 8 is explicit that money is grouped by currency and
  never summed across it, and a contact with one job abroad has a balance that
  cannot be written as one number. The flat version had no way to say so: it
  would have reported a figure in the wrong currency while looking correct.
  `is_overdue` belongs to the amount rather than to the person, because a
  contact can owe an overdue balance on last June's wedding and a deposit that
  is not due until spring. **The names are the resource's**, and they are
  written here because the seam had guessed three of them: the fixtures said
  `minor` and `overdue`, and had no `is_account_currency` at all, so the swap
  was a rename in the type and its two readers. `booking_count`, `next_booking`
  and `last_booking` are computed by the API and arrive on the payload; nothing
  derives them in a component, and `next_booking` and `last_booking` are whole
  objects so a list payload could drop the `bookings` array entirely.
- **A booking can have no event, and the screen had never been shown one.**
  `event_type`, `date`, `venue_name` and `city` are all absent together when a
  booking has nothing in its diary, which is what an enquiry that arrived
  before anybody named a day looks like. The fixtures could not produce it:
  they derived `next_booking` and `last_booking` out of the same dated array
  they used for `bookings`, so every fixture booking had a date by
  construction. On the endpoint the two rules are different, and the card's
  bookings list is the one that sees the null. Undated, a booking reads
  "No date yet" in place of its type and day, and sorts below every dated one,
  which is the rule `App\Services\ContactActivity::occasions()` uses at the
  other end. Before the guards it was not a missing line but a **crash**:
  `b.date.localeCompare(a.date)` threw inside the render and took the whole
  card with it.
- **`next_booking` and `last_booking` cannot be undated, and the screen does
  not rely on it.** `App\Services\ContactActivity::pick()` builds its
  candidates by flat-mapping the bookings' events, so a booking with none
  contributes nothing and it returns null rather than an occasion. The type
  keeps both fields nullable and `ContactRow` carries a fallback anyway,
  because that guarantee lives in one private method with no test naming it,
  and the alternative is asserting it from a file that cannot see it.
- **One payload, and every sort, group and filter happens in the browser.** Two
  hundred contacts after five years is about fifty kilobytes, so there is no
  pagination, no infinite scroll, no virtualisation and no spinner. The filter
  box is a FILTER and not a search: it narrows rows already in memory, with no
  request, which is why it has no debounce and no minimum length.
- **The filter's two rules that are not obvious.** A number is matched with the
  non-digits stripped from both sides, but only once three digits have been
  typed, because with fewer every contact whose number contains that run
  matches and the list appears not to filter at all. Text is matched folded
  through NFD, so an unaccented query finds an accented name.
- **The row's second line is always the nearest booking, never the phone
  number**, and it is shortened deliberately rather than left to truncate: the
  event label goes when the event is the main one, the year goes when it is
  this year, and only the part of the venue before the first comma is used. All
  three drop the part that would survive a truncation while the identifying
  part would not.
- **One pill at most, in precedence order**: overdue, then owing, then a
  confirmed future booking. Both money pills go when the amounts-owed setting
  is off and the row then falls through to Upcoming, because switching money
  off is a request to hide figures and not a request to hide the diary.
  **A contact owing in two currencies gets the first entry rather than the
  account's**: there is one pill and there are two amounts, and the endpoint's
  order, which is by currency code, decides. On a GBP account that is the euros.
  It is the wrong row rather than a wrong figure, because `formatMoney` is
  passed the amount's own currency and the pill reads "Owes €500"; the amount
  and its symbol cannot disagree. `is_account_currency` is on every entry, so
  preferring the account's is one line in `pillFor` on the day somebody decides
  it should be. The fixtures never held two currencies, so this had never been
  reachable.
- **The line-shortening and the settings mechanism are both shared now.**
  `venueShort` and the date-and-place line moved to `src/lib/eventLine.ts` when
  the enquiries row needed the same three rules, and the localStorage reader
  moved to `src/lib/viewSettings.ts` when the enquiries menu needed the same
  wrapped accessor and per-field checking. Both kept `contactList.ts` and
  `contactView.ts`'s public surfaces exactly, so every contacts test passes
  unchanged, which is the bar an extraction has to clear: one that edits its
  own tests has stopped being one.
- **The four view settings live on the device, in one localStorage key.** Not
  on the account, not in a column, no request. Every read and write is wrapped,
  and not only the parse: a private window and a browser blocking site data
  make the accessor itself throw before there is any JSON to fail on, so a try
  around `JSON.parse` alone would still take the screen down. Each field is
  checked rather than cast, so a `sort` written by an older build cannot reach
  the sort function.
- **The list is a listbox and the filter field is its combobox.** Arrowing has
  to leave focus in the field, so the arrow keys move a cursor the field points
  at with `aria-activedescendant` and never move focus. Each band is a
  `role="group"` named by its own heading, which is both the valid ARIA
  structure and what gives each sticky heading its own containing block: in one
  flat list every heading pins to the same line at once and only paint order
  decides which is visible. `aria-selected` is the keyboard cursor and
  `aria-current` is whose card is open, because on a wide screen both are on
  screen at once and they mean different things.
- **The split is the same container query the calendar uses**,
  `--container-split`, on `<main>`. Below it the two columns are one at a time,
  done by hiding one rather than by a second route. The list column is a fixed
  **400px**, and the number was measured: at 360 three of the twenty-two demo
  rows cut their second line, worst by 32 pixels, because a pill takes about
  ninety; at 380 one row is still 12 short; at 400 nothing truncates. It costs
  the detail forty pixels, which at an 834px tablet leaves it 362 and still
  comfortable.
- **One document scroll container, as everywhere else.** The detail is
  `position: sticky` with `align-self: start`, so the list scrolls and the card
  stays put with no height worked out from the viewport, and a card taller than
  the list simply scrolls with the page, which is right.
- **`ContactViewMenu.vue` is the four settings and nothing else.** The panel
  around it is `ui/AnchoredSheet.vue`, aligned right so that three hundred
  pixels of panel stay over the list column rather than spilling across the
  detail. **This is where "extract from two, never from one" was paid off**:
  the rule used to say a third panel of this shape was the moment to extract
  one, and the enquiries screen bringing two more is what made it that moment.
- **The refusal and the confirm are two different controls.** Deleting a
  contact with bookings is refused, because schema 5.7 restricts
  `bookings.contact_id`, and that is said as one line at the moment Delete is
  tapped rather than as a disabled button or standing small print. The refusal
  is the kit's `Notice`, a polite live region that takes no focus and clears
  itself after four and a half seconds. The confirm is
  `ContactDeleteDialog.vue`, a real dialog with a focus trap and no timer,
  because a confirm that vanishes while you are reading it decides for you, and
  an irreversible action inside a live region is markup that tells a screen
  reader it is a passive announcement.
- **The chip is a class in `app.css`, beside `check` and `radio`**, not a
  component: half its uses are ordinary links (`tel:`, `sms:`, `mailto:` and a
  maps URL, the same at both sizes with nothing sniffing the platform) and half
  are buttons. It completes a grammar the rest of the app should follow: a
  filled accent button makes something new, a subtle accent chip acts on what
  is already there, and the danger chip is destructive.

- **`meta.truncated` arrives and nothing draws it.** The endpoint caps the
  response at `config/contacts.php`'s 1000 and says so in `meta`; the store
  holds it and no part of the screen reads it, so an account over the ceiling
  sees a list that is quietly short. That is a gap the swap created rather than
  one it closed, and it needs a decision about what to say and where before it
  needs code.

Not built yet on that screen: adding, editing and saving a contact to the
phone, which all need endpoints or flows that do not exist, the two create
buttons on the card, which carry the same TODO `CreateMenu` does, and deleting,
which is still local to the store because there is no delete route.

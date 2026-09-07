# The contacts screen

`/contacts` is a list beside one person's card, and it is the second screen
built against a seam rather than against the API. The endpoint,
`GET /api/contacts`, exists now and the screen has not been moved onto it, so
`src/lib/contactFixtures.ts` still stands in: it exports `loadContacts()`,
`src/stores/contacts.ts` is its only caller, and a component reaching past the
store to it is a test failure rather than a convention. **That file is deleted
when the screen moves**, `src/lib/contacts.ts` is written the way
`src/lib/bookings.ts` is, the front-end type takes the resource's field names,
and nothing else in the feature changes.

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
- **`outstanding` is an array of `{currency, minor, overdue}`, not a figure and
  a flag.** Schema section 8 is explicit that money is grouped by currency and
  never summed across it, and a contact with one job abroad has a balance that
  cannot be written as one number. The flat version had no way to say so: it
  would have reported a figure in the wrong currency while looking correct.
  `overdue` belongs to the amount rather than to the person, because a contact
  can owe an overdue balance on last June's wedding and a deposit that is not
  due until spring. `booking_count`, `next_booking` and `last_booking` are
  computed by the API and arrive on the payload; nothing derives them in a
  component, and `next_booking` and `last_booking` are whole objects so a list
  payload could drop the `bookings` array entirely.
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

Not built yet on that screen: adding, editing and saving a contact to the
phone, which all need endpoints or flows that do not exist, and the two
create buttons on the card, which carry the same TODO `CreateMenu` does.

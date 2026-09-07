# The app screens, and what Vitest covers

It also has its shell, and every route behind the sign-in exists as a page.
Most of the shell is still furniture rather than features: apart from the
authentication screens, My Account, Home, Bookings and Enquiries, **nothing in
it calls the API**. A page that has not been built says so. The one place
invented data survives is `src/lib/contactFixtures.ts`, which stands in for
`GET /api/contacts` until the contacts screen is moved onto it; every person,
venue and address in it is made up, because this screen is what gets
screenshotted.

- The routes, all children of the layout route: `/`, `/attention`,
  `/bookings`, `/bookings/:id`, `/enquiries`, `/enquiries/:id`, `/contacts`
  and `/contacts/:id`, `/more`, `/help`, `/account` and its four pages,
  `/settings` and its ten groups, plus `/billing` on the web target. A detail
  route that has not been built echoes its `:id` and looks nothing up;
  `/contacts/:id` is a real page and is a child of `/contacts` rather than a
  sibling, so the two share one mount.
- Every page that has not been built is one shared `PlaceholderView.vue`: a
  header and a card saying so. A route names its title with `meta.titleKey`,
  which is also the document title, and where a phone's back link goes with
  `meta.backTo`. When a section is really built it gets a view of its own and
  the routes array points at that instead.
- The pages that are real: `HomeView` (the three blocks of business logic
  section 18, with `/attention` as the second of them uncapped), `MoreView` (the phone's overflow list and sign out),
  the settings index, `/settings/travel`, which is the one honest example of
  the form kit doing a section's work and saves nothing because there is no
  settings API yet, all of My account, `/bookings`, `/contacts` with
  `/contacts/:id`, and `/enquiries` with `/enquiries/:id`, which is the first
  screen in the app that writes to a booking.
- **My account is the first section that reads and writes real data.** Its
  index is a list built from `accountGroups`, and its four pages are: your
  details, which is one form over two endpoints and sends only the half that
  changed; your password, which keeps you signed in on this device and says
  the others were not; devices, which lists the tokens from
  `GET /api/auth/tokens` and revokes one at a time; and email and marketing,
  which is read-only apart from a resend and one consent toggle that saves the
  moment it is thrown and goes back if the request fails.
- The document title comes from the route's locale key, there is a skip link
  to `<main>`, every route has one `<h1>` in it, and both navigations are
  real `<nav>` landmarks. **On Contacts that one `<h1>` is the section's, and
  it stays "Contacts" at every width**, including a phone showing one person's
  card. The alternative was a heading that became the person's name below the
  split, and which of the two it is cannot be known without measuring, because
  the split is a container query. So the card's name is an `<h2>` and the way
  back to the list is a link at the top of it.

Vitest covers what will actually break: the navigation config's derived
lists and its idea of what is current, that both navigations render one item
per entry and mark the right one, that the pill resolves on a deep link,
the sheet's open, close and focus behaviour, FormField's wiring, that a
button says it is busy while its request is in flight, that a rejected field
puts the message on that field and moves focus to it, that a second submit
sends nothing while the first is still going, that the username check is
announced in words as well as drawn, and, on My account, that the details form
sends only the half that changed and says which of its two requests failed,
that a password mismatch is caught before any request, that the devices list
gets its empty state, its unrevokable current row and a revoke that keeps a
row it could not remove, and that the consent toggle sends on change and goes
back on a failure. On Contacts it covers the rules rather than the rendering,
because all of them are plain functions: that recency puts an upcoming booking
above a past one and orders upcoming ascending, that A to Z sorts on the
surname where there is one and puts everybody without one in Other, that three
digits match a number stored with spaces and two do not, that an unaccented
query finds an accented name, that the second line drops what it is supposed to
drop and keeps what it is supposed to keep, that the pill precedence holds and
both money pills go when the setting is off, that the view settings survive a
reload, and that they fall back to the defaults when the storage accessor
itself throws rather than when it is merely empty. Then the five tests that
read the source of the app rather than run it:
`boundary.test.ts`, which stops business logic leaking into components,
`styleRules.test.ts`, which stops a `dark:` variant, an arbitrary value or a
hex colour reaching a component, `lib/bookings.guards.test.ts`,
`lib/contacts.guards.test.ts` and `lib/enquiries.guards.test.ts`, which stop a
day key being built from `toISOString`, a component importing the contacts
fixtures and the enquiries list becoming a listbox, all five on the scaffold
in `lib/sourceRules.ts`, and `router/routeNames.test.ts`, which fails
if any route name written down anywhere is not a route that exists. Renaming
a route is the change that breaks a `router.push` in a screen nobody opened,
and a route name is a string, so nothing else can catch it. Component tests
mount through `src/lib/testMount.ts`, a few lines of `createApp` with the
real router, i18n, pinia and the global kit, because there is no component
testing library and there is not going to be one.

# What Vitest covers

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
data module and the enquiries list becoming a listbox, all five on the scaffold
in `lib/sourceRules.ts`, and `router/routeNames.test.ts`, which fails
if any route name written down anywhere is not a route that exists. Renaming
a route is the change that breaks a `router.push` in a screen nobody opened,
and a route name is a string, so nothing else can catch it. Component tests
mount through `src/lib/testMount.ts`, a few lines of `createApp` with the
real router, i18n, pinia and the global kit, because there is no component
testing library and there is not going to be one.

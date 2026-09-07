# The enquiry detail and the stage write

`GET /api/enquiries/{booking}` and `PATCH /api/enquiries/{booking}`, both on
`App\Http\Controllers\EnquiryController` beside the list.

- **The detail is a resource composing the list's resource, not a second
  answer.** `EnquiryDetailResource` resolves `EnquiryResource` and spreads it,
  then adds `enquiry_message`, `party_size` and `notes`. So the detail cannot
  decide which event a booking means, what it is waiting on or what it clashes
  with for itself. The two alternatives were both ways for it to: one resource
  with the extra fields would make every row in a five-hundred-row list pay for
  a notes load, or make one resource read a flag two ways, which is what
  `ContactBookingResource`'s own docblock rejects; two independent shapes would
  give the detail its own copy of three computed answers. A test asserts the
  detail's list half is identical to the list's row for the same record.
- **`enquiry_message` is why the detail route exists.** Business logic 5.5.1
  keeps the source on the record so that when an extraction is wrong the artist
  can see what it was working from, and it is the difference between a name from
  four months ago and a conversation that can be picked up. It is also a pasted
  WhatsApp thread, which is exactly why it is not on the list: 19.3's no-signal
  rule is about the booking screen on a wedding morning, not a detail opened by
  tapping a row.
- **`party_size` is null at zero, never nought.** A party of nobody is not
  something anybody books, so a nought could only mean "the party sheet is
  empty", which is "not known yet" wearing a number. Same rule as `total_minor`,
  one field along.
- **`notes` is the booking's stream only.** Schema 5.17 makes both `booking_id`
  and `contact_id` nullable with a check that one is set, so a note can belong
  to the person rather than to the job; that one is not a note about this
  enquiry and belongs on the contact's card. Each entry carries `id`, `body` and
  `created_at` as a UTC instant. No author, because collaborators do not exist
  in v1 and every note is the owner's.
- **The write is one route taking a stage, not `/convert` and `/lost`.** Named
  routes are how a state machine is expressed, and this matrix is deliberately
  not one: any of the six stages moves to any other and the artist decides. The
  day somebody adds a precondition to a `/convert` route because the route's
  existence invites one, decision 235 has quietly acquired an inference. The
  side effects also argue for it, being symmetric: `converted_at` is set on the
  way into provisional and cleared on the way out, so one write does both and
  splitting it would put the clearing half where nobody looking at `/convert`
  would find it.
- **It is not a general booking update.** It names `stage` and `lost_reason`,
  reads only `validated()`, and a test asserts the contact, the currency, the
  message, the dates, the lines and the hold are all unchanged after a write
  that tried to send them.
- **`Booking::LISTED_STAGES` and `Booking::SETTABLE_STAGES`, and the asymmetry
  between them is deliberate.** The list and the detail read show five stages,
  the four live plus `lost`. The write accepts six, those plus `provisional`,
  because converting is reversible until something is signed (business logic
  5.3). **So after converting, the client holds an object it may PATCH back but
  may not GET.** That is right rather than an oversight: an undo works because it
  PATCHes, and a refresh 404s because the row belongs to the bookings list now.
  It is also exactly what a front-end developer meets at eleven at night, which
  is why it is here.
- **A booking at confirmed or beyond is refused with a 422, not a 403**, and the
  refusal lives in `UpdateEnquiryStageRequest::after()`. The caller is allowed to
  be here; the record is the wrong kind, and changing the stage of a signed job
  through a route built for a list of maybes is a downgrade. A policy would
  answer the wrong question. The error hangs off `stage` because that is the
  only field the request has, so the field is where the message renders rather
  than a claim the value sent was invalid; `EventController::refuseIfTooMany()`
  puts a range-size error on `from` for the same reason.
- **`lost_reason` is `prohibited_unless` rather than `missing_unless`.** A
  reason sent with any other stage is refused; an explicit `null` is not,
  because a client that always sends both fields and puts null in the second
  when there is no reason is saying something true.
- **Both validation rules are built from the enums**, `Rule::enum(...)->only(...)`
  against `Booking::SETTABLE_STAGES`, never a list of values typed a second
  time, which is the rule the check constraints already follow.
- **`hold_expires_at` is written on every stage change by
  `App\Services\SoftHold`, and `account_settings.hold_days` is its length.**
  When this route was written neither existed: no write path set the column,
  so `artist_not_held` could only fire for rows a seeder set by hand, and the
  hold length was a setting with nowhere to live. That is what changed, and
  `docs/soft-hold.md` is the record of it. The write here calls `SoftHold` the
  way it calls `touchActivity()`, so the highest-precedence value on the
  waiting-on axis, the one that sits above money because the date itself can be
  lost, is reachable in real use.

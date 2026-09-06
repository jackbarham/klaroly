<?php

/*
 * The numbers the bookings and enquiries endpoints need that are not columns.
 *
 * Nothing outside config/ reads env(), so these live here rather than in the
 * services and controllers that use them.
 *
 * The enquiries cap is here rather than in a config/enquiries.php of its own,
 * which is the opposite call from config/contacts.php. That file's reason is
 * about nouns: a contact is a different thing from a booking, so a contacts
 * cap filed under bookings is one nobody finds. An enquiry is the SAME noun at
 * an earlier stage, per business logic 4.3 and decision 235, and the number
 * the enquiries screen turns on most, cold_enquiry_days, is already here and
 * is read by the home screen too. A file named for the screen that did not
 * hold that number would be the confusing split.
 */
return [

    /*
     * How long an enquiry at Possible may sit untouched before the waiting-on
     * axis calls it cold, per business logic section 6.
     *
     * Three weeks is past "she is still thinking about it" and short enough
     * that a clash on a summer Saturday is still worth a phone call, which is
     * the decision the figure exists to inform (business logic 5.2). There is
     * no account_settings column for this yet; when there is, it reads from
     * there and this becomes the fallback, and that is a one-line change
     * because this is the only place the number is written.
     */
    'cold_enquiry_days' => 21,

    /*
     * The widest range GET /api/events will answer, in days, and the most
     * events it will return.
     *
     * An endpoint whose cost is set by its caller is one somebody trips over
     * eventually, so from=1900&to=2100 is refused rather than served. Five
     * years is longer than any artist books ahead and covers an established
     * account's whole history; past that it is a scrape. Two thousand events
     * is about a thousand bookings at two events each, comfortably a decade of
     * a full book, and around 400KB of JSON, which is where a phone on a poor
     * connection starts to suffer.
     *
     * Neither can fire on the call the app actually makes. `from` defaults to
     * today, the span cap applies only when both ends are given, and nobody
     * has two thousand events ahead of them. They are reachable only by a
     * deliberately wide historical request, which is exactly where a 422
     * asking for a narrower range is the right answer.
     */
    'max_span_days' => 1830,
    'max_events' => 2000,

    /*
     * The most enquiries GET /api/enquiries will return in one response.
     *
     * Twenty to forty live enquiries is the working set, and the live set is
     * not what makes this necessary: `lost` comes back too, and `lost`
     * accumulates for ever. Five hundred covers a busy artist's forty live
     * plus a decade of archive, and the measured figure for the demo account
     * is in the report for this prompt rather than guessed at.
     *
     * A ceiling rather than a refusal, which is the call GET /api/contacts
     * makes and the opposite of max_events above. The events cap is reachable
     * only by a deliberately wide range, so a 422 asking for a narrower one is
     * an answer the caller can act on; a caller here sends no parameters and
     * so cannot ask for less, and refusing would leave that account with a
     * dead screen.
     *
     * The ordering is what makes truncation survivable: lost sorts last, so
     * the first thing a truncated response drops is the archive, which is the
     * half the screen shows behind a switch.
     */
    'max_enquiries' => 500,

    /*
     * The most attention rows GET /api/home will return, and how many upcoming
     * events it sends with them.
     *
     * **The attention block is not naturally bounded, which is the thing to
     * know here.** It reads as the artist's open workload, so forty-ish, and
     * that intuition is wrong: artist_enquiry_cold fires on every live enquiry
     * nobody has touched for cold_enquiry_days, so an artist who does not open
     * the app for a month has every one of them on this list, and
     * max_enquiries above is 500. The array is longest in exactly the
     * situation the screen exists for.
     *
     * A hundred is comfortably past the honest working set, past what anybody
     * reads behind a See all, and about 25KB of JSON. Truncation is survivable
     * because decision 217's precedence is the sort order, so the tail dropped
     * is client_signature and never an overdue balance. The owed headline is
     * summed before the cap for the same reason; see HomeController.
     *
     * Six upcoming, where the screen draws three and business logic 18.2 says
     * "the next two or three". Sending exactly three is how an endpoint becomes
     * one screen's private API, and it leaves nothing behind when a same-day
     * event passes. Six is a glance at the rest of the week and still cheap:
     * each row carries a party count and a travel estimate, so it is a heavier
     * object than a bookings-list row.
     */
    'max_attention' => 100,
    'home_upcoming' => 6,

    /*
     * Whether the intake form exists at all.
     *
     * Section 7.4 of the schema, intake_forms and intake_questions, is
     * designed and not migrated, so no form can be sent and none can come
     * back. That is a different thing from an artist switching the feature
     * off, and the waiting-on axis has to tell them apart: with the feature
     * merely off she has chosen not to use a thing that works, and gating on
     * that would be gating on a promise the system can keep. Gating on this
     * flag gates on one it cannot.
     *
     * Three branches of App\Services\WaitingOnResolver read it through one
     * method. The day 7.4 is migrated this becomes true, client_form and
     * artist_review need their real conditions written, artist_price stops
     * falling back, and then this flag is deleted.
     */
    'intake_available' => false,

];

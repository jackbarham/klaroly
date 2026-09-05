<?php

/*
 * The numbers the bookings endpoints need that are not columns.
 *
 * Nothing outside config/ reads env(), so these live here rather than in the
 * services and controllers that use them.
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

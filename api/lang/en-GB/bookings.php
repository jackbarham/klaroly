<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bookings Language Lines
    |--------------------------------------------------------------------------
    |
    | The bookings and calendar endpoints. Names of stages, event types and
    | waiting-on values are not here: those are enum values the app
    | translates itself, the same way it does the username availability
    | reason, so the API never decides what a stage is called on screen.
    |
    */

    'range_backwards' => 'The end of the range comes before the start.',
    'range_too_wide' => 'That range is too wide. Ask for :days days or fewer.',
    'too_many_events' => 'That range holds more than :count events. Ask for a narrower one.',

    // PATCH /api/enquiries/{booking}. The stage names themselves are not here:
    // those are enum values the app translates, so the API never decides what
    // a stage is called on screen.
    'not_an_enquiry' => 'This booking is no longer an enquiry, so its stage cannot be changed here.',
    'stage_not_settable' => 'That is not a stage an enquiry can be moved to.',
    'lost_reason_required' => 'Say why this enquiry is not going ahead.',
    'lost_reason_not_allowed' => 'A reason belongs only on an enquiry that is not going ahead.',
    'lost_reason_unknown' => 'That is not a reason this app records.',

];

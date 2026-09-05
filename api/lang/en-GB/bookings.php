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

];

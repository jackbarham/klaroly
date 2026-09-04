<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transactional Email Language Lines
    |--------------------------------------------------------------------------
    |
    | One group per notification class in App\Notifications. Key names
    | describe what a line is for, not what it says.
    |
    */

    'password_changed' => [
        'subject' => 'Your Klaroly password was changed',
        'changed' => 'The password for the Klaroly account :email has just been changed.',
        'signed_out' => 'Every other device and browser has been signed out. Sign in again with the new password.',
        'not_you' => 'If this was not you, reset your password straight away using the button below, then reply to this email so we can help.',
        'action' => 'Reset your password',
    ],

];

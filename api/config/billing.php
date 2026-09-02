<?php

/*
 * Billing values that are not Cashier's own. Cashier's settings live in
 * config/cashier.php.
 */
return [

    // Days of free trial a new account gets at registration. Written to
    // accounts.trial_ends_at, which is Cashier's own column.
    'trial_days' => 30,

];

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired personal access tokens stop working the moment they expire; this
// only clears the rows out a day later.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

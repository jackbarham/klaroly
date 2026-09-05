<?php

use Illuminate\Support\Facades\Schedule;

// Expired personal access tokens stop working the moment they expire; this
// only clears the rows out a day later.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

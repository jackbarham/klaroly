<?php

use Illuminate\Support\Facades\Route;

// The API has no HTML pages. Fortify registers its own authentication routes
// (login, logout, register, password reset) at the root, without a prefix.
Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
    ]);
});

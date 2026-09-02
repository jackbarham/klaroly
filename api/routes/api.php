<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Routes here are prefixed with /api. The sanctum guard accepts either the
// web app's session cookie or a mobile bearer token, so one route serves both.
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

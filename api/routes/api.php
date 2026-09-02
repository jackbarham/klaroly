<?php

use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\UsernameAvailabilityController;
use App\Http\Middleware\NormaliseEmail;
use Illuminate\Support\Facades\Route;

// Routes here are prefixed with /api. The sanctum guard accepts either the
// web app's session cookie or a mobile bearer token, so one route serves
// both. Fortify's own routes (login, logout, register, password reset, email
// verification, profile and password update) are registered at the root
// without the prefix and inside the web middleware group, so they need the
// CSRF cookie and, for some, a session. The web app uses them.
//
// A bearer-token caller cannot, so four of them have JSON twins under
// /api/auth (decision 87): register, forgot-password, reset-password and
// email/verification-notification. Each twin is stateless and reuses
// Fortify's actions and responses, so the two paths cannot drift. Login has
// no twin because POST /api/auth/token is the mobile login. Profile and
// password update twins arrive with the settings screen.

// Unauthenticated.
Route::post('/auth/token', [TokenController::class, 'store'])
    ->middleware([NormaliseEmail::class, 'throttle:token']);

Route::post('/auth/register', [RegisterController::class, 'store'])
    ->middleware([NormaliseEmail::class, 'throttle:register']);

Route::post('/auth/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware([NormaliseEmail::class, 'throttle:forgot-password']);

Route::post('/auth/reset-password', [NewPasswordController::class, 'store'])
    ->middleware(NormaliseEmail::class);

Route::get('/usernames/{username}', UsernameAvailabilityController::class)
    ->middleware('throttle:30,1');

// Authenticated. Every route in here has the current account bound, so the
// scoped models return that account's rows and nothing else.
//
// Email verification is sent but not enforced (decision 83). When
// enforcement arrives, add the 'verified' alias to this group's middleware
// and nowhere else.
Route::middleware(['auth:sanctum', 'account'])->group(function () {
    Route::get('/me', MeController::class);

    Route::get('/auth/tokens', [TokenController::class, 'index']);
    Route::delete('/auth/tokens/{id}', [TokenController::class, 'destroy'])->whereNumber('id');
    Route::delete('/auth/token', [TokenController::class, 'destroyCurrent']);

    Route::post('/auth/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1');
});

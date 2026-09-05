<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\Auth\UpdatePasswordController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MarketingConsentController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\ProfileInformationController;
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
// A bearer-token caller cannot, so six of them have JSON twins here
// (decision 87): register, forgot-password, reset-password and
// email/verification-notification under /api/auth, and profile and password
// update at /api/user/profile-information and /api/user/password. Each twin
// is stateless and reuses Fortify's actions and responses, so the two paths
// cannot drift. The two settings twins keep Fortify's own paths on purpose:
// a twin is the same route without the session, and one under a different
// name invites the question of whether it also behaves differently. Login
// has no twin because POST /api/auth/token is the mobile login.

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

    // What the My Account screen writes. Every one of these except the
    // password answers with the same payload as GET /api/me, so the app
    // replaces its stored "me" in one hop instead of following each write
    // with a read.
    Route::put('/user/profile-information', [ProfileInformationController::class, 'update'])
        ->middleware([NormaliseEmail::class, 'throttle:profile-update']);

    // Nothing in "me" changes, so this one answers with a message. It is
    // limited because it revokes credentials.
    Route::put('/user/password', [UpdatePasswordController::class, 'update'])
        ->middleware('throttle:password-update');

    Route::patch('/account', [AccountController::class, 'update']);

    Route::put('/user/marketing-consent', [MarketingConsentController::class, 'update']);

    // What the bookings screen reads. The windowed one is the list and the
    // calendar; the months one is the jump sheet's dots and the bounds of its
    // year strip. They are separate because folding them together would mean
    // either no dots or fetching the whole diary to draw twelve of them.
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/months', [EventController::class, 'months']);
});

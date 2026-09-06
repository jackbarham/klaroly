<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\Auth\UpdatePasswordController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
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

    // What the home screen reads: business logic section 18's three blocks in
    // one payload. One route rather than three, because decision
    // 2026-09-06.1954 makes the owed headline the sum of the attention block's
    // client_balance rows: split them and either the money route recomputes
    // every booking's waiting-on state to produce one number, or the client
    // sums the rows itself and holds the definition of a money figure. That
    // Home is the screen which most has to work with no signal (business logic
    // 23.2) is the second reason and not the first.
    Route::get('/home', [HomeController::class, 'index']);

    // What the bookings screen reads. The windowed one is the list and the
    // calendar; the months one is the jump sheet's dots and the bounds of its
    // year strip. They are separate because folding them together would mean
    // either no dots or fetching the whole diary to draw twelve of them.
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/months', [EventController::class, 'months']);

    // What the contacts screen reads. One payload, no parameters: the screen
    // holds the whole list and does its own sorting, grouping and filtering
    // with no round trip, which is what makes it instant and what makes it
    // work with no signal. A ceiling in config/contacts.php keeps the cost
    // bounded, and it is a flag in the meta block rather than a refusal,
    // because a caller that sends no parameters cannot ask for less.
    Route::get('/contacts', [ContactController::class, 'index']);

    // What the enquiries screen reads. There is no enquiries table: business
    // logic 4.3 is one bookings table with a stage column, and this route
    // returns the bookings at the early stages, one row per enquiry rather
    // than one per event (decision 234). Same shape of payload as contacts,
    // and no stage parameter: the stage set is the endpoint, and the screen's
    // groups are the waiting-on axis and the staleness bands rather than the
    // stage.
    Route::get('/enquiries', [EnquiryController::class, 'index']);

    // One enquiry opened, and the one way a record crosses the line between
    // the two lists (decision 235). The write takes a stage rather than
    // splitting into /convert and /lost, because the matrix is deliberately
    // not a state machine: named routes are how a state machine is expressed,
    // and the first precondition somebody adds to a /convert route is an
    // inference that decision says nothing makes. Both answer with the same
    // detail shape, so the screen replaces what it is holding rather than
    // refetching.
    //
    // {booking} is bound by the router, which works because bootstrap/app.php
    // puts BindCurrentAccount ahead of SubstituteBindings; without that the
    // binding query runs with no tenant and every row is a 404.
    Route::get('/enquiries/{booking}', [EnquiryController::class, 'show']);
    Route::patch('/enquiries/{booking}', [EnquiryController::class, 'update']);
});

<?php

use App\Enums\BookingStage;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Event;
use App\Models\User;
use App\Support\CurrentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

// Feature tests boot the application and run against the klaroly_test
// Postgres database, rebuilt once per run and wrapped in a transaction per
// test. Unit tests do not boot the application.
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/*
 * The enquiry key constants live in this file rather than beside the tests
 * that assert them, because three test files need them now. A constant
 * declared in a sibling test file only exists when Pest has collected that
 * sibling, so running one of the three on its own would die on an undefined
 * constant.
 */

/**
 * The keys GET /api/enquiries promises, mirroring app/src/types/enquiries.ts.
 *
 * Prompt 18 writes that file against this list. It is the contract and this is
 * its twin: if one moves without the other, the app reads undefined from a
 * field the API renamed and nothing fails until somebody opens the screen, so
 * the two are pinned together here on purpose.
 */
const ENQUIRY_KEYS = [
    'id',
    'stage',
    'client_name',
    'contact_id',
    'source',
    'source_booking',
    'last_touched_at',
    'waiting_on',
    'total_minor',
    'currency',
    'event',
    'has_trial',
    'lost_reason',
    'lost_side',
    'clash',
];

const ENQUIRY_EVENT_KEYS = [
    'type',
    'date',
    'location_type',
    'venue_name',
    'city',
];

const SOURCE_BOOKING_KEYS = [
    'id',
    'client_name',
    'date',
];

const CLASH_KEYS = [
    'confirmed',
    'provisional',
    'others',
];

/**
 * The three GET /api/enquiries/{booking} adds, and the shape of one note.
 *
 * They are asserted as ENQUIRY_KEYS followed by these, never as a list of
 * seventeen typed out, because the detail resource composes the list resource
 * and the assertion has to be able to fail when only one of the two moves.
 */
const ENQUIRY_DETAIL_KEYS = [
    'enquiry_message',
    'party_size',
    'notes',
];

const ENQUIRY_NOTE_KEYS = [
    'id',
    'body',
    'created_at',
];

/**
 * Create an account with a settings row and make it the current tenant.
 *
 * @param  array<string, mixed>  $settings
 */
function actingForAccount(array $settings = []): Account
{
    $account = Account::factory()->withSettings($settings)->create();

    app(CurrentAccount::class)->set($account);

    return $account->load('settings');
}

function currentAccount(): CurrentAccount
{
    return app(CurrentAccount::class);
}

/**
 * Create a user who owns an account with a settings row, the shape
 * registration produces, without going through the registration route.
 * The account is not bound as current; the request under test does that.
 *
 * @param  array<string, mixed>  $userAttributes
 */
function createOwner(array $userAttributes = [], ?Account $account = null): User
{
    $account ??= Account::factory()->withSettings()->create();

    $user = User::factory()->create($userAttributes + ['last_account_id' => $account->id]);

    AccountUser::factory()->owner()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);

    return $user;
}

/**
 * The same, for a collaborator: a member of the account who is not its
 * owner, which is what the permission checks turn on.
 *
 * @param  array<string, mixed>  $userAttributes
 */
function createCollaborator(array $userAttributes = [], ?Account $account = null): User
{
    $account ??= Account::factory()->withSettings()->create();

    $user = User::factory()->create($userAttributes + ['last_account_id' => $account->id]);

    AccountUser::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);

    return $user;
}

/**
 * A session row for a user, as the database session driver would write one.
 * Returns its id so a test can name the session it expects to survive.
 */
function sessionRow(User $user, ?string $id = null): string
{
    $id ??= Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    return $id;
}

/**
 * Make a request the way the web app makes one: signed in, credentialed, and
 * carrying a session cookie naming a row a test planted with sessionRow().
 *
 * It exists because getting any part of this wrong produces a test that
 * passes while proving the opposite of what it claims. A JSON request sends
 * no cookies at all unless it says it is credentialed, and Sanctum only
 * starts a session when the referer is one of its stateful domains. Without
 * both, request()->hasSession() is false, PasswordChanger is told to keep
 * nothing, and an assertion that the other session rows were deleted passes
 * because every row was deleted, including the one that should have
 * survived.
 *
 * The referer is the web app's own origin, which is also the host Sanctum is
 * configured to treat as stateful. Those are two separate environment
 * variables, so the one thing this helper cannot do is assume they still
 * agree: if they ever drift, it would silently stop starting a session and
 * quietly reintroduce the false green it was written to prevent. Hence the
 * check below, which fails loudly and names both settings.
 *
 * Fortify's own routes are in the web group and need no referer, but they
 * ignore the one this sends, so both halves of a parity test can be written
 * the same way.
 */
function actingAsWebApp(TestCase $test, User $user, string $sessionId): TestCase
{
    $host = parse_url(config('app.frontend_url'), PHP_URL_HOST);
    $stateful = array_map('trim', config('sanctum.stateful', []));

    TestCase::assertContains($host, $stateful, sprintf(
        'FRONTEND_URL is %s but SANCTUM_STATEFUL_DOMAINS is %s. Sanctum would not treat a request from the web app as stateful, no session would be started, and every test using this helper would pass without proving anything.',
        config('app.frontend_url'),
        implode(', ', $stateful),
    ));

    return $test->actingAs($user)
        ->withCredentials()
        ->withHeader('Referer', config('app.frontend_url'))
        ->withCookie(config('session.cookie'), $sessionId);
}

/**
 * An account with the feature map registration actually writes, made current.
 *
 * The settings factory defaults features to an empty map, and an absent key is
 * off, so a test that used the plain factory would watch feature suppression
 * swallow whatever it was asserting and read as a service that does not work.
 *
 * @param  array<string, bool>  $overrides
 */
function accountWithFeatures(array $overrides = []): Account
{
    return actingForAccount(['features' => $overrides + config('features.defaults')]);
}

/**
 * An owner of an account with that same realistic feature map, not bound as
 * current, for a test that makes a request rather than calling a service.
 *
 * @param  array<string, bool>  $features
 */
function bookingsOwner(array $features = []): User
{
    $account = Account::factory()
        ->withSettings(['features' => $features + config('features.defaults')])
        ->create();

    return createOwner([], $account);
}

/**
 * One event on a booking of the given stage, on the given date. Confirmed
 * unless the caller says otherwise, so a test that is about dates does not
 * have to say anything about stages.
 *
 * @param  array<string, mixed>  $bookingAttributes
 * @param  array<string, mixed>  $eventAttributes
 */
function eventOn(string $date, array $bookingAttributes = [], array $eventAttributes = []): Event
{
    $booking = Booking::factory()->create($bookingAttributes + [
        'stage' => BookingStage::Confirmed,
    ]);

    return Event::factory()->create($eventAttributes + [
        'booking_id' => $booking->id,
        'event_date' => $date,
    ]);
}

/**
 * A contact with one booking, one main event on the given date, and nothing
 * else. The shape almost every contacts test starts from.
 *
 * The current account must already be bound: it is the tenant every row is
 * created under, and binding it here would hide the thing several of these
 * tests are about.
 *
 * @param  array<string, mixed>  $contactAttributes
 * @param  array<string, mixed>  $bookingAttributes
 * @param  array<string, mixed>  $eventAttributes
 */
function contactWithBooking(
    string $date,
    array $contactAttributes = [],
    array $bookingAttributes = [],
    array $eventAttributes = [],
): Contact {
    $contact = Contact::factory()->create($contactAttributes);

    $booking = Booking::factory()->create($bookingAttributes + [
        'contact_id' => $contact->id,
        'stage' => BookingStage::Confirmed,
    ]);

    Event::factory()->create($eventAttributes + [
        'booking_id' => $booking->id,
        'event_date' => $date,
    ]);

    return $contact;
}

/**
 * One enquiry, with a contact of its own and at most one event.
 *
 * A date of null is a real and common case rather than an omission: "next
 * summer, we have not booked the venue yet" is one of the most winnable kinds
 * of enquiry there is, and it is the case an events-shaped payload cannot
 * represent at all.
 *
 * The current account must already be bound: it is the tenant every row is
 * created under, and binding it here would hide what the tenancy tests are
 * about.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $eventAttributes
 */
function enquiry(
    BookingStage $stage,
    ?string $date = null,
    array $attributes = [],
    array $eventAttributes = [],
): Booking {
    $booking = Booking::factory()->create($attributes + [
        'stage' => $stage,
        'contact_id' => Contact::factory(),
    ]);

    if ($date !== null) {
        Event::factory()->create($eventAttributes + [
            'booking_id' => $booking->id,
            'event_date' => $date,
        ]);
    }

    return $booking;
}

/**
 * An enquiry nobody has touched for longer than the cold threshold.
 */
function coldEnquiry(BookingStage $stage, ?string $date = null): Booking
{
    return enquiry($stage, $date, [
        'last_touched_at' => now()->subDays(config('bookings.cold_enquiry_days') + 1),
    ]);
}

/**
 * A valid registration body. The mobile twin adds device_name through the
 * overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registration(array $overrides = []): array
{
    return $overrides + [
        'business_name' => 'Ellie Marsh Makeup',
        'name' => 'Ellie Marsh',
        'email' => 'ellie@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ];
}

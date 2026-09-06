<?php

use App\Enums\BookingStage;
use App\Enums\EndingSide;
use App\Enums\LostReason;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Support\CurrentAccount;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

// The demo account is what gets screenshotted, shown to an artist and handed
// to Apple. These assert that it can still demonstrate the two cases the
// bookings screen exists for.
//
// It is not a nicety. The gap existed because nothing was watching: the demo
// had no day carrying more than one event at all, so it could not show the
// case business logic 19.1 names as the reason for the whole screen. A seeder
// is exactly the kind of file that gets edited for one reason and quietly
// loses a property it was carrying for another.

// The stages a mark reads as taken, per decision 187: the work is on, or was
// worked. Asserted as sets rather than as exact stages, because the second day
// below is seeded as completed when its date has already passed, and the
// screen depends on the property rather than on the row anybody happened to
// write.
const HELD_STAGES = [BookingStage::Confirmed, BookingStage::Completed, BookingStage::Closed];

// The soft hold from business logic 5.1, which is what the count badge counts.
const ENQUIRY_STAGES = [BookingStage::Possible, BookingStage::Quoted];

/**
 * Every seeded date, with the stages of the bookings whose events fall on it.
 *
 * @return array<string, array<int, BookingStage>>
 */
function stagesByDate(): array
{
    $user = User::where('email', 'ellie@example.com')->firstOrFail();

    app(CurrentAccount::class)->set($user->accounts()->first());

    $dates = [];

    foreach (Event::with('booking')->get() as $event) {
        $dates[$event->event_date->format('Y-m-d')][] = $event->booking->stage;
    }

    return $dates;
}

/**
 * @param  array<int, BookingStage>  $stages
 * @param  array<int, BookingStage>  $wanted
 */
function countIn(array $stages, array $wanted): int
{
    return count(array_filter($stages, fn (BookingStage $stage) => in_array($stage, $wanted, true)));
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

// The counts are exact, not "two or more", and that is deliberate.
//
// These two days are not a general property of the seeder, they are a curated
// picture, and the whole file exists to produce it. "Two or more" was
// satisfied silently when a trial landed on the two-wedding day by arithmetic
// coincidence, which made the label read "3 confirmed bookings" and put a ten
// o'clock trial between a half five start and a one o'clock ceremony. Exact
// counts would be wrong for a general invariant and are right here: a third
// booking arriving on either day should be red, because the assertion's job is
// to say the demo still shows the thing it was built to show.
//
// It would still not catch that day being impossible rather than merely
// crowded. Nothing cheap would. It catches the count changing, which is what
// led anybody to look.

// Business logic 19.1: "a day with one confirmed booking and three live
// enquiries shows both, which is the situation the artist most needs to see at
// a glance". If this fails, the demo cannot show the feature it is a demo of.
it('has one day carrying exactly one booking and exactly three live enquiries', function () {
    $matching = array_filter(
        stagesByDate(),
        fn (array $stages) => countIn($stages, HELD_STAGES) === 1 && countIn($stages, ENQUIRY_STAGES) === 3,
    );

    expect($matching)->toHaveCount(1);
});

// Business logic 5.2: two weddings in a day is normal, and the clash warning
// exists because of it rather than in spite of it.
it('has one day carrying exactly two bookings and nothing else', function () {
    $matching = array_filter(
        stagesByDate(),
        fn (array $stages) => countIn($stages, HELD_STAGES) === 2 && count($stages) === 2,
    );

    expect($matching)->toHaveCount(1);
});

// The busy Saturday has to be in the future, or three live enquiries against
// it are nonsense rather than demo data, and it has to be a Saturday because
// that is where this trade's clashes actually happen.
it('puts the busy day on a Saturday still to come', function () {
    $busy = array_filter(
        stagesByDate(),
        fn (array $stages) => countIn($stages, HELD_STAGES) === 1 && countIn($stages, ENQUIRY_STAGES) === 3,
    );

    foreach (array_keys($busy) as $date) {
        expect(CarbonImmutable::parse($date)->isSaturday())->toBeTrue()
            ->and(CarbonImmutable::parse($date)->greaterThanOrEqualTo(today()))->toBeTrue();
    }
});

// A mark on screen the moment the app opens, without navigating a month.
it('puts a day with two bookings inside the month the calendar opens on', function () {
    $twoBookings = array_filter(
        stagesByDate(),
        fn (array $stages) => countIn($stages, HELD_STAGES) === 2 && count($stages) === 2,
    );

    $thisMonth = array_filter(
        array_keys($twoBookings),
        fn (string $date) => CarbonImmutable::parse($date)->isSameMonth(today()),
    );

    expect($thisMonth)->not->toBeEmpty();
});

// Decision 224, extended to addresses, and the sweep decision 225 asks for:
// the columns and the prose both. A real name in an enquiry_message is not
// found by reading the columns, which is how one survived the first pass.
//
// A regression guard rather than a general check: it cannot tell whether an
// invented street exists somewhere, only that the real ones that were here
// have not come back. That is the failure worth guarding, because these were
// removed deliberately and the way they return is somebody restoring a line.
/**
 * What the enquiries screen needs the demo to hold.
 *
 * Same reasoning as the two days above and the same failure: the account had
 * no enquiry at in_conversation and none without a date, so GET /api/enquiries
 * could be built, seeded and screenshotted without either of the two cases
 * decision 234 names as the reason it is one row per enquiry rather than one
 * per event. Asserted as properties rather than as counts, because a
 * fourteenth enquiry arriving is not a regression and a missing stage is.
 */
describe('the enquiries the demo has to be able to show', function () {
    beforeEach(function () {
        $user = User::where('email', 'ellie@example.com')->firstOrFail();

        app(CurrentAccount::class)->set($user->accounts()->first());
    });

    it('holds at least one enquiry at every live stage', function () {
        $stages = Booking::query()->pluck('stage')->all();

        foreach (Booking::ENQUIRY_STAGES as $stage) {
            expect($stages)->toContain($stage);
        }
    });

    // "Next summer, we have not booked the venue yet" is normal, often
    // winnable, and the case an events-shaped payload cannot represent at all,
    // because there is no row for it.
    it('holds an enquiry with no date at all', function () {
        $dateless = Booking::query()
            ->whereIn('stage', Booking::ENQUIRY_STAGES)
            ->doesntHave('events')
            ->count();

        expect($dateless)->toBeGreaterThan(0);
    });

    // An enquiry ends two ways and the demo has to show both, or the pill that
    // tells them apart has nothing to draw. Paired deliberately: a seeder that
    // lost the artist's side would otherwise pass on the client's alone.
    it('holds one enquiry each side ended', function () {
        $sides = Booking::query()
            ->where('stage', BookingStage::Lost)
            ->pluck('lost_reason')
            ->filter()
            ->map(fn (LostReason $reason) => $reason->side())
            ->unique()
            ->values()
            ->all();

        expect($sides)->toContain(EndingSide::Client)
            ->and($sides)->toContain(EndingSide::Artist);
    });

    // The widened artist_enquiry_cold branch has to have something to report,
    // or the screen's "Gone quiet" group is empty in every screenshot.
    it('holds an enquiry nobody has touched for longer than the cold threshold', function () {
        $cold = Booking::query()
            ->whereIn('stage', Booking::ENQUIRY_STAGES)
            ->where('last_touched_at', '<', now()->subDays(config('bookings.cold_enquiry_days')))
            ->count();

        expect($cold)->toBeGreaterThan(0);
    });
});

it('names no real venue and no real address, in a column or in a sentence', function () {
    $seeder = file_get_contents(database_path('seeders/DemoAccountSeeder.php'));

    $venues = [
        'Clevedon Hall', 'Priston Mill', 'Elmore Court', 'Deer Park', 'Coombe Lodge',
        'Tortworth Court', 'Orchardleigh House', 'Tythe Barn', 'Ashton Court',
        'Assembly Rooms', 'Berwick Lodge', 'Kings Weston House', 'Town Hall', 'Pump Room',
    ];

    // A venue is a business that welcomes being named. A street address beside
    // an invented person's name and wedding date points at a house where
    // actual people live, which is the worse of the two.
    $addresses = [
        'Chandos Road', 'Widcombe Hill', 'Lansdown Road', 'Pennsylvania Road',
        'Staplegrove Road', 'London Road', 'Clevedon Road', 'Bath Road', 'Alma Road',
        'Sion Hill', 'Prestbury Road', 'Christchurch Street', 'Gloucester Road',
        'Dowdeswell Close', 'Cotham Hill', 'Elton Road', 'Bourne Lane', 'Bennett Street',
        'Berwick Drive', 'Kings Weston Lane', 'Imperial Square', 'Stall Street',
        'Frampton Lane', 'Nailsworth Road', 'Winchcombe Street', 'Bathford Hill',
        'Kelston Road', 'Westgate Street',
    ];

    $found = array_filter(
        array_merge($venues, $addresses),
        fn (string $name) => str_contains($seeder, $name),
    );

    expect(array_values($found))->toEqual([]);
});

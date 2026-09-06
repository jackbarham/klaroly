<?php

use App\Enums\AgreementStatus;
use App\Enums\BookingStage;
use App\Models\Agreement;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/*
 * **The cost is the thing to be careful about on this endpoint**, and it is
 * worse here than on GET /api/enquiries: that one asks the waiting-on axis of a
 * list filtered to the enquiry stages, and this asks it of every live booking
 * on the account.
 *
 * Measured before the eager load was written: an account with forty live
 * bookings, each carrying a contact, an event, a line, an issued invoice with
 * two payments and an agreement, issued **201 queries**. The eager load takes
 * the same work to a constant.
 *
 * Where the 201 went, per booking: contact, events, account, account.settings,
 * invoices, agreements, quotes at the enquiry stages, and then
 * Invoice::paidMinor() running its own sum twice per invoice, once through
 * outstandingMinor() in the balance branch and again through depositCovered()
 * in the deposit branch. It grew with the diary and with the money on each
 * booking, both.
 */

/**
 * An account holding the shape that costs the most: every relation the resolver
 * reads, on bookings at a spread of stages.
 */
function homeWorkload(int $bookings): void
{
    $stages = [BookingStage::Possible, BookingStage::Provisional, BookingStage::Confirmed, BookingStage::Quoted];

    for ($i = 0; $i < $bookings; $i++) {
        $booking = Booking::factory()->create([
            'contact_id' => Contact::factory(),
            'stage' => $stages[$i % 4],
            'hold_expires_at' => today()->subDay(),
            'last_touched_at' => now()->subDays(40),
        ]);

        BookingLine::factory()->create(['booking_id' => $booking->id]);
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->addDays($i + 1)]);

        // **Settled and past due on purpose.** WaitingOnResolver returns on the
        // first invoice waiting on something, so with unpaid invoices it looks
        // at exactly one however many there are, the count never grows, and the
        // test passes against an unfixed model while proving nothing. That is
        // the mistake EventIndexTest's own money test made first. Fully paid
        // invoices past their due date are the case where both the balance loop
        // and the deposit loop run to the end.
        $invoice = Invoice::factory()->issued($i + 1)->create([
            'booking_id' => $booking->id,
            'total_minor' => 45000,
            'deposit_minor' => 0,
            'balance_due_on' => today()->subDays(3),
        ]);

        Payment::factory()->count(2)->create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'amount_minor' => 22500,
            'paid_on' => today()->subDays(5),
        ]);

        Agreement::factory()->create([
            'booking_id' => $booking->id,
            'status' => AgreementStatus::Sent,
            'sent_at' => now()->subDays(11),
        ]);
    }
}

it('issues the same number of queries however much work is on the account', function () {
    $user = bookingsOwner();
    $account = $user->accounts()->first();

    $count = function (int $bookings) use ($user, $account) {
        currentAccount()->set($account);

        Payment::query()->delete();
        Invoice::query()->delete();
        Agreement::query()->delete();
        Event::query()->delete();
        BookingLine::query()->delete();
        Booking::query()->forceDelete();
        Contact::query()->forceDelete();

        homeWorkload($bookings);

        currentAccount()->clear();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->getJson('/api/home')->assertOk();

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queries;
    };

    /*
     * **Two assertions, and the literal is the more useful of the two.**
     *
     * The first is the N+1 guard: the cost must not grow with the diary. The
     * literal guards against the other failure, which is somebody adding a
     * constant-cost query per request — a fifth aggregate, an unbatched
     * existence check — and the flat-growth assertion staying green through it,
     * because the new query does not grow with the diary either. A number that
     * has to be edited deliberately is what makes that visible in a diff.
     *
     * Against 201 for the naive version at forty bookings.
     */
    expect($count(3))->toBe($count(40))->toBe(34);
});

/*
 * Paired with the count above, because making an endpoint cheap is only an
 * improvement if it still answers the same. The workload is built so that every
 * booking is waiting on something, and the payload has to say so.
 */
it('still reports what every booking is waiting on at the cheaper query count', function () {
    $user = bookingsOwner();
    currentAccount()->set($user->accounts()->first());

    homeWorkload(8);

    currentAccount()->clear();

    $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

    expect($response->json('data.attention'))->toHaveCount(8)
        ->and(array_values(array_unique(array_column($response->json('data.attention'), 'waiting_on'))))
        /*
         * The three this workload reaches, and which three is itself the
         * evidence that the resolver still ran: the invoices are settled, so
         * the money branches fall through to the agreement and every confirmed
         * booking answers client_signature. A resolver that had quietly stopped
         * answering would fail here rather than in an attention block nobody is
         * looking at.
         */
        ->toEqualCanonicalizing([
            'artist_not_held',
            'artist_enquiry_cold',
            'client_signature',
        ]);
});

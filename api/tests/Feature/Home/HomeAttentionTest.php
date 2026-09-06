<?php

use App\Enums\AgreementStatus;
use App\Enums\BookingStage;
use App\Enums\EventType;
use App\Enums\FeatureKey;
use App\Models\Agreement;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Payment;

// Business logic 18.1, and decision 217's precedence.

/**
 * A confirmed booking with an issued invoice past its balance date, which is
 * client_balance. The commonest money row and the one the owed headline sums.
 */
function overdueBalance(int $totalMinor = 68000, int $paidMinor = 34000, int $daysLate = 9): Booking
{
    // invoices is unique on (account_id, sequence), so a test creating two on
    // one account has to number them. Counting the account's own rows rather
    // than a static keeps it right across tests in one run.
    $sequence = Invoice::query()->count() + 1;

    $booking = Booking::factory()->confirmed()->create(['contact_id' => Contact::factory()]);

    $invoice = Invoice::factory()->issued($sequence)->create([
        'booking_id' => $booking->id,
        'total_minor' => $totalMinor,
        'deposit_minor' => 0,
        'balance_due_on' => today()->subDays($daysLate),
    ]);

    if ($paidMinor > 0) {
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'amount_minor' => $paidMinor,
        ]);
    }

    return $booking;
}

describe('the order', function () {
    /*
     * Decision 217, and the reason it matters more here than on the enquiries
     * list is the cap: the phone previews four rows, so an array grouped by
     * party rather than by precedence puts four of the artist's own rows at the
     * top and an overdue balance can never reach the preview. That was a real
     * bug, found by building the prototype.
     */
    it('returns the rows in decision 217\'s precedence order', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Created in an order that is not the answer, so a payload that
        // happened to come back in insertion order would fail.
        coldEnquiry(BookingStage::Quoted, today()->addMonths(4)->toDateString());
        overdueBalance();
        enquiry(BookingStage::Possible, today()->addMonths(8)->toDateString());
        Booking::factory()->provisional()->create(['hold_expires_at' => today()->subDay()]);

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

        expect(array_column($response->json('data.attention'), 'waiting_on'))->toBe([
            'artist_not_held',
            'client_balance',
            'artist_enquiry_cold',
            'artist_price',
        ]);
    });

    /*
     * The assertion the prototype's bug would have failed. Four artist rows
     * created first, then one client row, and the client row still has to be
     * inside the four the phone previews.
     */
    it('lets a client row reach the first four past any number of artist rows', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        coldEnquiry(BookingStage::New, today()->addMonths(3)->toDateString());
        coldEnquiry(BookingStage::InConversation, today()->addMonths(5)->toDateString());
        coldEnquiry(BookingStage::Quoted, today()->addMonths(7)->toDateString());
        enquiry(BookingStage::Possible, today()->addMonths(9)->toDateString());

        $overdue = overdueBalance();

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

        $preview = array_slice($response->json('data.attention'), 0, 4);

        expect(array_column($preview, 'waiting_on'))->toContain('client_balance')
            ->and(array_column($preview, 'booking_id'))->toContain($overdue->id);
    });

    /*
     * Oldest first, on whichever timestamp the value is about. Sorting every
     * value on last_touched_at instead would put the least overdue balance
     * above the most overdue one.
     */
    it('puts the oldest first inside one value, on that value\'s own timestamp', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $recent = overdueBalance(daysLate: 2);
        $ancient = overdueBalance(daysLate: 40);
        $middling = overdueBalance(daysLate: 15);

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

        expect(array_column($response->json('data.attention'), 'booking_id'))
            ->toBe([$ancient->id, $middling->id, $recent->id]);
    });

    it('returns the same order twice running', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Three rows identical on the value and on its timestamp, so only the
        // final tie-break separates them.
        overdueBalance();
        overdueBalance();
        overdueBalance();

        currentAccount()->clear();

        $first = $this->actingAs($user)->getJson('/api/home')->json('data.attention');
        $second = $this->actingAs($user)->getJson('/api/home')->json('data.attention');

        expect($first)->toBe($second);
    });
});

describe('what a row carries', function () {
    it('sends every key the app expects, and no sentence or day count', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        overdueBalance();

        currentAccount()->clear();

        $row = $this->actingAs($user)->getJson('/api/home')->assertOk()->json('data.attention.0');

        expect(array_keys($row))->toBe(HOME_ATTENTION_KEYS);

        // The wording is the app's, in its own locale file: a server that
        // writes UI copy is one that has to be redeployed to fix a typo.
        foreach ($row as $key => $value) {
            expect($key)->not->toBeIn(['sentence', 'title', 'message', 'detail', 'description']);
        }

        // And no day count. Every "9 days late" is worked out at render with
        // differenceInCalendarDays, because a number computed here is wrong by
        // the time a tab left open overnight reads it.
        expect($row)->not->toHaveKeys(['days_late', 'days_ago', 'days_until', 'age_days'])
            ->and($row['due_on'])->toBe(today()->subDays(9)->toDateString());
    });

    it('gives artist_not_held the hold and when it became provisional', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->provisional()->create([
            'hold_expires_at' => today()->subDays(2),
            'converted_at' => now()->subDays(16),
        ]);
        // Read back rather than compared against the in-memory value: the
        // column is a timestamp to the second and now() carries microseconds,
        // so the two differ in a way that says nothing about the endpoint.
        $convertedAt = $booking->fresh()->converted_at;
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => '2026-10-17']);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.attention.0.waiting_on', 'artist_not_held')
            ->assertJsonPath('data.attention.0.party', 'artist')
            ->assertJsonPath('data.attention.0.hold_expires_at', today()->subDays(2)->toDateString())
            ->assertJsonPath('data.attention.0.event_date', '2026-10-17')
            ->assertJsonPath('data.attention.0.converted_at', $convertedAt->toJSON());
    });

    it('gives artist_enquiry_cold the stage and when it was last touched', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = coldEnquiry(BookingStage::InConversation, '2026-10-18');

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.attention.0.waiting_on', 'artist_enquiry_cold')
            ->assertJsonPath('data.attention.0.stage', 'in_conversation')
            ->assertJsonPath('data.attention.0.event_date', '2026-10-18')
            ->assertJsonPath('data.attention.0.last_touched_at', $booking->last_touched_at->toJSON());
    });

    it('gives artist_price when the enquiry arrived', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = enquiry(BookingStage::Possible, '2027-07-04');

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.attention.0.waiting_on', 'artist_price')
            ->assertJsonPath('data.attention.0.created_at', $booking->created_at->toJSON())
            ->assertJsonPath('data.attention.0.event_date', '2027-07-04');
    });

    it('gives client_balance the amount, the invoice total and the due date', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        overdueBalance(totalMinor: 68000, paidMinor: 34000, daysLate: 9);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.attention.0.waiting_on', 'client_balance')
            ->assertJsonPath('data.attention.0.party', 'client')
            // "£340 of £680, 9 days late": what is left, what it was, and when.
            ->assertJsonPath('data.attention.0.outstanding_minor', 34000)
            ->assertJsonPath('data.attention.0.invoice_total_minor', 68000)
            ->assertJsonPath('data.attention.0.due_on', today()->subDays(9)->toDateString());
    });

    /*
     * outstanding_minor and due_on read against the deposit here and against
     * the balance above, and waiting_on is the discriminator. The deposit
     * figure is the SHORTFALL rather than the deposit, because a client who has
     * paid half of one owes the half.
     */
    /*
     * Decision 2026-09-06.2212, and the state schema 5.15 allows: "one invoice
     * per booking by default ... a second can be raised manually". The row was
     * always one per booking while its money was per invoice, so in this state
     * it named one of the two and under-reported the booking.
     */
    it('sums both overdue invoices on one booking, and dates the row from the older', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->confirmed()->create(['contact_id' => Contact::factory()]);

        // £340 still owing, 9 days late.
        $first = Invoice::factory()->issued(1)->create([
            'booking_id' => $booking->id,
            'total_minor' => 68000,
            'deposit_minor' => 0,
            'balance_due_on' => today()->subDays(9),
        ]);
        Payment::factory()->create(['invoice_id' => $first->id, 'booking_id' => $booking->id, 'amount_minor' => 34000]);

        // A second, raised manually, £200 still owing and 16 days late.
        $second = Invoice::factory()->issued(2)->create([
            'booking_id' => $booking->id,
            'total_minor' => 52000,
            'deposit_minor' => 0,
            'balance_due_on' => today()->subDays(16),
        ]);
        Payment::factory()->create(['invoice_id' => $second->id, 'booking_id' => $booking->id, 'amount_minor' => 32000]);

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

        // One row, because the unit was always the booking.
        expect($response->json('data.attention'))->toHaveCount(1);

        $response
            // "£540 of £1,200 · 16 days late", all three about the booking.
            ->assertJsonPath('data.attention.0.outstanding_minor', 54000)
            ->assertJsonPath('data.attention.0.invoice_total_minor', 120000)
            // The OLDER of the two, which is the number an artist says out loud
            // when chasing. Naming the newer would understate the problem.
            ->assertJsonPath('data.attention.0.due_on', today()->subDays(16)->toDateString())
            // And the headline agrees with the row by construction, because
            // there is only one sum.
            ->assertJsonPath('data.money.owed_minor', 54000)
            ->assertJsonPath('data.money.owed_count', 1);
    });

    /*
     * The half of the same rule that is not about money: a second invoice that
     * is not overdue is not part of the row's figures at all.
     */
    it('leaves an invoice that is not overdue out of the row\'s figures', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->confirmed()->create(['contact_id' => Contact::factory()]);

        $overdue = Invoice::factory()->issued(1)->create([
            'booking_id' => $booking->id,
            'total_minor' => 68000,
            'deposit_minor' => 0,
            'balance_due_on' => today()->subDays(9),
        ]);
        Payment::factory()->create(['invoice_id' => $overdue->id, 'booking_id' => $booking->id, 'amount_minor' => 34000]);

        // Due next month, so it is outstanding and nobody is waiting on it.
        Invoice::factory()->issued(2)->create([
            'booking_id' => $booking->id,
            'total_minor' => 90000,
            'deposit_minor' => 0,
            'balance_due_on' => today()->addDays(30),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.attention.0.outstanding_minor', 34000)
            ->assertJsonPath('data.attention.0.invoice_total_minor', 68000)
            // The presence half: the second invoice is real and is counted
            // where it belongs, so this is not passing on an invoice the
            // endpoint simply cannot see.
            ->assertJsonPath('data.money.outstanding.due_minor', 90000);
    });

    it('gives client_deposit the shortfall and the deposit\'s own due date', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->confirmed()->create();
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => '2026-09-26']);

        $invoice = Invoice::factory()->issued()->create([
            'booking_id' => $booking->id,
            'total_minor' => 68000,
            'deposit_minor' => 17000,
            'deposit_due_on' => today()->subDays(4),
            'balance_due_on' => today()->addDays(30),
        ]);
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'amount_minor' => 5000,
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.attention.0.waiting_on', 'client_deposit')
            ->assertJsonPath('data.attention.0.outstanding_minor', 12000)
            ->assertJsonPath('data.attention.0.due_on', today()->subDays(4)->toDateString())
            ->assertJsonPath('data.attention.0.event_date', '2026-09-26');
    });

    it('gives client_signature when the agreement went out', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = Booking::factory()->confirmed()->create();
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => '2027-06-12']);
        $agreement = Agreement::factory()->create([
            'booking_id' => $booking->id,
            'status' => AgreementStatus::Sent,
            'sent_at' => now()->subDays(11),
        ]);
        $sentAt = $agreement->fresh()->sent_at;

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.attention.0.waiting_on', 'client_signature')
            ->assertJsonPath('data.attention.0.party', 'client')
            ->assertJsonPath('data.attention.0.sent_at', $sentAt->toJSON())
            ->assertJsonPath('data.attention.0.event_date', '2027-06-12');
    });

    /*
     * artist_review names two dates rather than one, which is the only reason
     * trial_date is on the row at all. It cannot fire while intake_available is
     * false, so this asserts the field is correct on a row that can fire, which
     * is what the day 7.4 lands will depend on.
     */
    it('sends the trial date beside the wedding day', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = coldEnquiry(BookingStage::Quoted, '2027-05-22');
        Event::factory()->create([
            'booking_id' => $booking->id,
            'event_date' => '2027-03-14',
            'type' => EventType::Trial,
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.attention.0.event_date', '2027-05-22')
            ->assertJsonPath('data.attention.0.trial_date', '2027-03-14');
    });

    it('sends a null trial date on a booking with only a wedding day', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        coldEnquiry(BookingStage::Quoted, '2027-05-22');

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.attention.0.event_date', '2027-05-22')
            ->assertJsonPath('data.attention.0.trial_date', null);
    });
});

describe('what is suppressed', function () {
    it('says nothing about a lost or a cancelled booking', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Both carry an overdue balance, which would be a row on any live
        // record. An ending waits on nobody.
        foreach ([BookingStage::Lost, BookingStage::Cancelled] as $index => $stage) {
            $booking = Booking::factory()->create(['stage' => $stage]);
            Invoice::factory()->issued($index + 1)->create([
                'booking_id' => $booking->id,
                'deposit_minor' => 0,
                'balance_due_on' => today()->subDays(5),
            ]);
        }

        // The presence half, on an otherwise identical live record.
        $live = overdueBalance();

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

        expect($response->json('data.attention'))->toHaveCount(1)
            ->and($response->json('data.attention.0.booking_id'))->toBe($live->id);
    });

    /*
     * Decision 2026-09-06.1436. Snoozing means "I know, stop telling me", and
     * the row going with it is the point rather than a side effect.
     */
    it('says nothing about a snoozed overdue balance', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $snoozed = Booking::factory()->confirmed()->create();
        Invoice::factory()->issued(99)->snoozedUntil(today()->addWeek()->toDateString())->create([
            'booking_id' => $snoozed->id,
            'deposit_minor' => 0,
            'balance_due_on' => today()->subDays(6),
        ]);

        // The presence half: the same overdue balance without the snooze.
        $chased = overdueBalance();

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

        expect(array_column($response->json('data.attention'), 'booking_id'))
            ->toBe([$chased->id]);
    });

    /*
     * Decision 219: gate on whether a feature is AVAILABLE, not on whether it
     * is switched on. Both need intake_forms, which is schema 7.4 and designed
     * rather than migrated, so a demo account with every toggle on still cannot
     * reach them.
     */
    it('cannot reach artist_review or client_form while the intake form has no table', function () {
        expect(config('bookings.intake_available'))->toBeFalse();

        $user = bookingsOwner([FeatureKey::IntakeForms->value => true]);
        currentAccount()->set($user->accounts()->first());

        coldEnquiry(BookingStage::Quoted, today()->addMonths(6)->toDateString());
        overdueBalance();

        currentAccount()->clear();

        $values = array_column(
            $this->actingAs($user)->getJson('/api/home')->assertOk()->json('data.attention'),
            'waiting_on',
        );

        // The presence half again: the rows that CAN fire are there, so this is
        // not passing on an empty payload.
        expect($values)->not->toContain('artist_review')
            ->and($values)->not->toContain('client_form')
            ->and($values)->toContain('artist_enquiry_cold')
            ->and($values)->toContain('client_balance');
    });
});

describe('the cap', function () {
    it('returns everything and says so when under the limit', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        overdueBalance();
        overdueBalance();

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonCount(2, 'data.attention')
            ->assertJsonPath('meta.attention.total', 2)
            ->assertJsonPath('meta.attention.returned', 2)
            ->assertJsonPath('meta.attention.truncated', false);
    });

    /*
     * The cap keeps the most urgent rather than an arbitrary slice, which is
     * what makes truncation survivable: the tail dropped is the bottom of
     * decision 217's order and never an overdue balance.
     */
    it('keeps the most urgent and reports the real total', function () {
        config()->set('bookings.max_attention', 3);

        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        coldEnquiry(BookingStage::Quoted, today()->addMonths(2)->toDateString());
        coldEnquiry(BookingStage::New, today()->addMonths(3)->toDateString());
        coldEnquiry(BookingStage::InConversation, today()->addMonths(4)->toDateString());
        overdueBalance();
        Booking::factory()->provisional()->create(['hold_expires_at' => today()->subDay()]);

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

        expect(array_column($response->json('data.attention'), 'waiting_on'))
            ->toBe(['artist_not_held', 'client_balance', 'artist_enquiry_cold']);

        $response
            ->assertJsonPath('meta.attention.total', 5)
            ->assertJsonPath('meta.attention.returned', 3)
            ->assertJsonPath('meta.attention.truncated', true);
    });
});

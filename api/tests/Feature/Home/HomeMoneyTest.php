<?php

use App\Enums\BookingStage;
use App\Enums\FeatureKey;
use App\Models\Account;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Payment;

// Business logic 18.3, and decision 27's headline.

/**
 * A confirmed booking with an issued invoice, a payment against it and a main
 * event on the given date.
 *
 * @param  array<string, mixed>  $invoiceAttributes
 */
function paidBooking(string $eventDate, int $totalMinor, ?int $paidMinor = null, ?string $paidOn = null, array $invoiceAttributes = []): Booking
{
    // invoices is unique on (account_id, sequence), so every one a test creates
    // has to be numbered.
    $sequence = Invoice::query()->count() + 1;

    $booking = Booking::factory()->confirmed()->create();
    Event::factory()->create(['booking_id' => $booking->id, 'event_date' => $eventDate]);
    BookingLine::factory()->create([
        'booking_id' => $booking->id,
        'quantity' => 1,
        'unit_price_minor' => $totalMinor,
    ]);

    $invoice = Invoice::factory()->issued($sequence)->create($invoiceAttributes + [
        'booking_id' => $booking->id,
        'total_minor' => $totalMinor,
        'deposit_minor' => 0,
    ]);

    if ($paidMinor !== null) {
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'amount_minor' => $paidMinor,
            'paid_on' => $paidOn ?? today()->toDateString(),
        ]);
    }

    return $booking;
}

describe('the feature toggles', function () {
    /*
     * Business logic 21.2 says a toggle "removes the related items from the
     * home screen's attention block". The attention block, named, and nothing
     * about the figures. An earlier reading took the money block away with them
     * and decision 2026-09-06.1946 corrects it: a booking carries a price
     * whether or not anybody ever raised an invoice for it.
     */
    it('removes exactly the deposit and balance rows with invoicing off, and keeps the money block', function () {
        $user = bookingsOwner([FeatureKey::Invoicing->value => false]);
        currentAccount()->set($user->accounts()->first());

        // Would be client_balance and client_deposit with invoicing on.
        $balance = Booking::factory()->confirmed()->create();
        Invoice::factory()->issued(1)->create([
            'booking_id' => $balance->id,
            'deposit_minor' => 0,
            'balance_due_on' => today()->subDays(5),
        ]);

        $deposit = Booking::factory()->confirmed()->create();
        Invoice::factory()->issued(2)->create([
            'booking_id' => $deposit->id,
            'deposit_minor' => 11250,
            'balance_due_on' => today()->addDays(30),
        ]);

        // The presence half: a row invoicing has nothing to do with, so this
        // cannot pass on an empty attention block.
        coldEnquiry(BookingStage::Quoted, today()->addMonths(4)->toDateString());

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

        expect(array_column($response->json('data.attention'), 'waiting_on'))
            ->toBe(['artist_enquiry_cold']);

        $response
            // The block is there, and the figures that do not depend on an
            // invoice are still computed.
            ->assertJsonPath('meta.features.invoicing', false)
            ->assertJsonPath('data.money.basis', 'payments')
            ->assertJsonPath('data.money.booked_ahead_minor', 0)
            // Null rather than absent: nothing was ever given a due date, so
            // the question has no answer on this account rather than an answer
            // of nothing.
            ->assertJsonPath('data.money.owed_minor', null)
            ->assertJsonPath('data.money.owed_count', null)
            ->assertJsonPath('data.money.outstanding', null);

        expect(array_keys($response->json('data.money')))->toBe(HOME_MONEY_KEYS);
    });

    /*
     * The interesting state, per the prototype: with every money feature off
     * the period figure stops being cash and becomes value. Those are different
     * numbers and the block has to say which it is showing rather than leaving
     * the screen to infer it from an empty key.
     */
    it('turns the period figure into booked value with payment tracking off, and says so in meta', function () {
        $user = bookingsOwner([
            FeatureKey::Invoicing->value => false,
            FeatureKey::PaymentTracking->value => false,
        ]);
        currentAccount()->set($user->accounts()->first());

        // A wedding worked earlier this month, priced at £560 and never
        // invoiced. Earlier this month rather than last, because the period
        // starts on the first: a booking dated before that is correctly outside
        // it and the test would be asserting the wrong thing.
        $booking = Booking::factory()->create(['stage' => BookingStage::Completed]);
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->startOfMonth()]);
        BookingLine::factory()->create([
            'booking_id' => $booking->id,
            'quantity' => 1,
            'unit_price_minor' => 56000,
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('meta.features.payment_tracking', false)
            // Said on the field, never by omission, so the screen cannot draw
            // "Received: £0" on an account that records no payments.
            ->assertJsonPath('data.money.basis', 'booking_value')
            ->assertJsonPath('data.money.periods.this_month.value_minor', 56000)
            ->assertJsonPath('data.money.periods.this_month.booking_count', 1)
            ->assertJsonPath('data.money.periods.this_month.average_value_minor', 56000);
    });

    it('says the basis is payments on an account that tracks them', function () {
        $user = bookingsOwner();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.basis', 'payments')
            ->assertJsonPath('meta.features.payment_tracking', true);
    });

    it('leaves booked ahead and provisional alone whatever the toggles say', function () {
        $user = bookingsOwner([
            FeatureKey::Invoicing->value => false,
            FeatureKey::PaymentTracking->value => false,
        ]);
        currentAccount()->set($user->accounts()->first());

        $confirmed = Booking::factory()->confirmed()->create();
        Event::factory()->create(['booking_id' => $confirmed->id, 'event_date' => today()->addMonths(2)]);
        BookingLine::factory()->create(['booking_id' => $confirmed->id, 'quantity' => 1, 'unit_price_minor' => 83400]);

        $held = Booking::factory()->provisional()->create();
        Event::factory()->create(['booking_id' => $held->id, 'event_date' => today()->addMonths(3)]);
        BookingLine::factory()->create(['booking_id' => $held->id, 'quantity' => 1, 'unit_price_minor' => 21000]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.booked_ahead_minor', 83400)
            ->assertJsonPath('data.money.booked_ahead_count', 1)
            // Never added to the figure above: a held date is not money, and a
            // figure mixing the two would be the most optimistic in the app.
            ->assertJsonPath('data.money.provisional_minor', 21000)
            ->assertJsonPath('data.money.provisional_count', 1);
    });
});

describe('the owed headline', function () {
    /*
     * Decision 2026-09-06.1954: the headline is the sum of the client_balance
     * rows and not a second query. The row is a task with a name on it and the
     * figure is the size of the problem, and the day they disagree is the day
     * the screen shows two rows and a total that does not match them.
     */
    it('equals the sum of the client_balance rows, with a snoozed one in neither', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Three overdue balances: £340, £200 and £480 still owing.
        paidBooking(today()->subDays(15)->toDateString(), 68000, 34000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(9),
        ]);
        paidBooking(today()->subDays(22)->toDateString(), 50000, 30000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(14),
        ]);
        paidBooking(today()->subDays(36)->toDateString(), 96000, 48000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(21),
        ]);

        // And a fourth, snoozed, which is in neither the rows nor the figure.
        paidBooking(today()->subDays(9)->toDateString(), 40000, 10000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(4),
            'reminders_snoozed_until' => today()->addWeek(),
        ]);

        currentAccount()->clear();

        $response = $this->actingAs($user)->getJson('/api/home')->assertOk();

        $rows = array_filter(
            $response->json('data.attention'),
            fn (array $row) => $row['waiting_on'] === 'client_balance',
        );

        expect($rows)->toHaveCount(3)
            ->and($response->json('data.money.owed_count'))->toBe(3)
            // And the snoozed one is named rather than simply gone: £300 of the
            // £400 invoice is still owed, and the artist can see she parked it.
            ->and($response->json('data.money.snoozed_minor'))->toBe(30000)
            // The assertion that holds the two together, computed from the
            // payload's own rows rather than from a figure typed twice.
            ->and($response->json('data.money.owed_minor'))
            ->toBe(array_sum(array_column($rows, 'outstanding_minor')))
            ->toBe(34000 + 20000 + 48000);
    });

    /*
     * The tension between the cap and decision 2026-09-06.1954, resolved on
     * purpose: the headline is summed before the cap, so a truncated response
     * shows a figure larger than its visible rows add up to. That is the right
     * side to be wrong on, because the figure is the size of the problem rather
     * than the size of the list, and meta.attention.truncated says so.
     */
    it('sums every balance row even when the cap hid some of them', function () {
        config()->set('bookings.max_attention', 1);

        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        paidBooking(today()->subDays(15)->toDateString(), 68000, 34000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(9),
        ]);
        paidBooking(today()->subDays(22)->toDateString(), 50000, 30000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(14),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonCount(1, 'data.attention')
            ->assertJsonPath('meta.attention.truncated', true)
            // Both, not just the one that survived.
            ->assertJsonPath('data.money.owed_count', 2)
            ->assertJsonPath('data.money.owed_minor', 54000);
    });

    /*
     * outstanding is its own query and answers a different question: a booking
     * with an invoice due next month is outstanding and waiting on nobody, so
     * it has no row and is in the due half rather than the overdue one.
     */
    it('splits outstanding into due and overdue, counting invoices with no row', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Overdue: £340 of £680.
        paidBooking(today()->subDays(15)->toDateString(), 68000, 34000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(9),
        ]);

        // Due but not late: £510 on a wedding in two months, so no row at all.
        paidBooking(today()->addMonths(2)->toDateString(), 51000, null, invoiceAttributes: [
            'balance_due_on' => today()->addDays(20),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.outstanding.overdue_minor', 34000)
            ->assertJsonPath('data.money.outstanding.due_minor', 51000)
            ->assertJsonPath('data.money.owed_minor', 34000);
    });
});

describe('the snoozed figure', function () {
    /*
     * Decision 27's own reason for existing. That decision says an artist who
     * can only stop the chasers by marking an invoice paid will mark it paid,
     * and the earnings figures then quietly become wrong. The snooze is the
     * honest escape hatch, and one that silently shrinks the headline teaches
     * artists to distrust the headline instead.
     */
    it('names what a snooze took out of the headline', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Chased: £340 still owing, in the headline.
        paidBooking(today()->subDays(15)->toDateString(), 68000, 34000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(9),
        ]);

        // Snoozed: £250 still owing, out of the headline and named here.
        paidBooking(today()->subDays(20)->toDateString(), 50000, 25000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(12),
            'reminders_snoozed_until' => today()->addWeek(),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            // The headline is only the chased one, which is the snooze doing
            // its job.
            ->assertJsonPath('data.money.owed_minor', 34000)
            ->assertJsonPath('data.money.owed_count', 1)
            // And the money it took out is still on the screen.
            ->assertJsonPath('data.money.snoozed_minor', 25000);
    });

    /*
     * A snoozed invoice is still LATE. The snooze suppresses the chasing and
     * not the fact, so counting that money as merely "due" would report a
     * fortnight-late balance as if it were not late.
     */
    it('counts snoozed money as overdue rather than as due', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        paidBooking(today()->subDays(20)->toDateString(), 50000, 25000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(12),
            'reminders_snoozed_until' => today()->addWeek(),
        ]);

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.outstanding.overdue_minor', 25000)
            ->assertJsonPath('data.money.outstanding.due_minor', 0)
            // A subset of overdue above, never something to add to it.
            ->assertJsonPath('data.money.outstanding.snoozed_minor', 25000);
    });

    it('is nought when nothing is snoozed, and null when invoicing is off', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // The presence half: a real overdue balance that nobody has snoozed, so
        // this is not passing on an account with no money on it at all.
        paidBooking(today()->subDays(15)->toDateString(), 68000, 34000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(9),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.owed_minor', 34000)
            ->assertJsonPath('data.money.snoozed_minor', 0);

        $off = bookingsOwner([FeatureKey::Invoicing->value => false]);

        $this->actingAs($off)->getJson('/api/home')->assertOk()
            // Null on the same toggle as the headline: with no invoicing
            // nothing was ever given a due date, so there is nothing to snooze.
            ->assertJsonPath('data.money.snoozed_minor', null);
    });

    it('stops counting a snooze that has run out', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Yesterday, so the pause is over and the row is back.
        $booking = paidBooking(today()->subDays(20)->toDateString(), 50000, 25000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(12),
            'reminders_snoozed_until' => today()->subDay(),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.snoozed_minor', 0)
            ->assertJsonPath('data.money.owed_minor', 25000)
            ->assertJsonPath('data.attention.0.booking_id', $booking->id)
            ->assertJsonPath('data.attention.0.waiting_on', 'client_balance');
    });

    /*
     * A lost booking can carry a snoozed overdue invoice and it is not the
     * artist's open workload, so it must not appear in a figure that sits
     * beside the headline. Reading the same live bookings the attention rows
     * came from is what makes that true for free.
     */
    it('ignores a snoozed invoice on a lost booking', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $lost = paidBooking(today()->subDays(20)->toDateString(), 50000, 25000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(12),
            'reminders_snoozed_until' => today()->addWeek(),
        ]);
        $lost->forceFill(['stage' => BookingStage::Lost])->save();

        // The presence half, on a live booking.
        paidBooking(today()->subDays(18)->toDateString(), 40000, 10000, invoiceAttributes: [
            'balance_due_on' => today()->subDays(7),
            'reminders_snoozed_until' => today()->addWeek(),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.snoozed_minor', 30000);
    });
});

describe('received', function () {
    /*
     * Cash basis, which is what a sole trader's tax return is on. A payment
     * recorded in September against an August invoice is September's money.
     */
    it('reports on the payment date and not on the invoice date', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        // Issued and dated well before this month; paid this month. The invoice
        // date and the payment date fall in different periods on purpose, so a
        // figure built off issued_on would give a different answer.
        paidBooking(
            today()->subMonths(2)->toDateString(),
            45000,
            45000,
            paidOn: today()->toDateString(),
            invoiceAttributes: ['issued_on' => today()->subMonths(2)],
        );

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            // In this month, because that is when the money arrived.
            ->assertJsonPath('data.money.periods.this_month.value_minor', 45000)
            ->assertJsonPath('data.money.periods.this_month.booking_count', 1);
    });

    it('leaves a payment made before the period out of it', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        paidBooking(
            today()->subMonths(5)->toDateString(),
            45000,
            45000,
            paidOn: today()->subMonths(5)->toDateString(),
        );

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.periods.this_month.value_minor', 0)
            // The presence half: it is in the twelve-month window, so this is
            // not a payment the endpoint simply cannot see.
            ->assertJsonPath('data.money.periods.twelve_months.value_minor', 45000);
    });

    it('counts a wedding paid in instalments once', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        $booking = paidBooking(today()->subDays(4)->toDateString(), 60000, 20000);
        $invoice = Invoice::query()->where('booking_id', $booking->id)->sole();

        Payment::factory()->count(2)->create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'amount_minor' => 20000,
            'paid_on' => today()->toDateString(),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.periods.this_month.value_minor', 60000)
            ->assertJsonPath('data.money.periods.this_month.booking_count', 1)
            ->assertJsonPath('data.money.periods.this_month.average_value_minor', 60000);
    });
});

describe('the periods', function () {
    /*
     * Business logic 18.3: sole traders are commonly on 6 April rather than a
     * calendar year, which is why account_settings carries the month and day.
     */
    it('starts the business year on the configured date and not on 1 January', function () {
        $user = bookingsOwner();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath(
                'data.money.periods.business_year.from',
                today()->month >= 4 && ! (today()->month === 4 && today()->day < 6)
                    ? today()->year.'-04-06'
                    : (today()->year - 1).'-04-06',
            );
    });

    /*
     * The presence half of the assertion above, and what makes it mean
     * something: with the setting moved the boundary moves with it, so the test
     * is about the column rather than about a hardcoded April.
     */
    it('follows the setting when the business year is a calendar year', function () {
        $account = Account::factory()->withSettings([
            'business_year_start_month' => 1,
            'business_year_start_day' => 1,
        ])->create();
        $user = createOwner([], $account);

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.periods.business_year.from', today()->year.'-01-01');
    });

    /*
     * Carbon overflows rather than refusing, so create(2027, 2, 29) is quietly
     * 1 March. An artist on a 29 February business year would have it start on
     * a different date in three years out of four, with nothing reporting it.
     */
    it('clamps a 29 February start to the end of the month in a common year', function () {
        $account = Account::factory()->withSettings([
            'business_year_start_month' => 2,
            'business_year_start_day' => 29,
        ])->create();
        $user = createOwner([], $account);

        // 2026 is a common year, so the start is the 28th and never 1 March.
        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.periods.business_year.from', '2026-02-28');
    });

    it('ends every period today, so a wedding still ahead is booked ahead rather than this month', function () {
        $user = bookingsOwner([
            FeatureKey::Invoicing->value => false,
            FeatureKey::PaymentTracking->value => false,
        ]);
        currentAccount()->set($user->accounts()->first());

        // Later this month, so a period ending at the month end would count it.
        $booking = Booking::factory()->confirmed()->create();
        Event::factory()->create(['booking_id' => $booking->id, 'event_date' => today()->addDays(2)]);
        BookingLine::factory()->create(['booking_id' => $booking->id, 'quantity' => 1, 'unit_price_minor' => 70000]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.periods.this_month.to', today()->toDateString())
            ->assertJsonPath('data.money.periods.this_month.value_minor', 0)
            ->assertJsonPath('data.money.booked_ahead_minor', 70000);
    });
});

describe('other currencies', function () {
    it('leaves work in another currency out and says the figures do', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        paidBooking(today()->subDays(3)->toDateString(), 45000, 45000);

        $abroad = Booking::factory()->confirmed()->create(['currency' => 'EUR']);
        Event::factory()->create(['booking_id' => $abroad->id, 'event_date' => today()->subDays(2)]);
        $invoice = Invoice::factory()->issued(99)->create([
            'booking_id' => $abroad->id,
            'currency' => 'EUR',
            'total_minor' => 30000,
            'deposit_minor' => 0,
        ]);
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'booking_id' => $abroad->id,
            'amount_minor' => 30000,
            'paid_on' => today()->toDateString(),
        ]);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.currency', 'GBP')
            // Schema section 8 lets a money tile be filtered to the account
            // currency rather than grouped by it. This flag is what stops the
            // filtered figure being a silent lie.
            ->assertJsonPath('data.money.excludes_other_currencies', true)
            ->assertJsonPath('data.money.periods.this_month.value_minor', 45000);
    });

    it('says nothing is left out on an account working in one currency', function () {
        $user = bookingsOwner();
        currentAccount()->set($user->accounts()->first());

        paidBooking(today()->subDays(3)->toDateString(), 45000, 45000);

        currentAccount()->clear();

        $this->actingAs($user)->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.money.excludes_other_currencies', false);
    });
});

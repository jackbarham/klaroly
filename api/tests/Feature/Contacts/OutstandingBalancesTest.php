<?php

use App\Enums\BookingStage;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\OutstandingBalances;
use Carbon\CarbonImmutable;

/**
 * The service on its own, without going through HTTP.
 *
 * ContactIndexTest already covers the same rules through the endpoint. These
 * exist for the parts a response body cannot show: that the grouping is by
 * currency rather than by booking, and that the overdue answer is the artist's
 * day rather than the application's, which is a one-hour-a-day difference that
 * no test running at noon would ever notice.
 */
beforeEach(function () {
    $this->account = actingForAccount();
    $this->contact = Contact::factory()->create();
    $this->service = app(OutstandingBalances::class);
});

function owedBooking(Contact $contact, string $currency = 'GBP'): Booking
{
    return Booking::factory()->create([
        'contact_id' => $contact->id,
        'stage' => BookingStage::Confirmed,
        'currency' => $currency,
    ]);
}

function issue(Booking $booking, int $totalMinor, array $attributes = []): Invoice
{
    static $sequence = 0;

    return Invoice::factory()->issued(++$sequence)->create($attributes + [
        'booking_id' => $booking->id,
        'currency' => $booking->currency,
        'total_minor' => $totalMinor,
        'deposit_minor' => 0,
    ]);
}

it('returns nothing for a contact with no bookings', function () {
    expect($this->service->for($this->contact->fresh()->load('bookings')))->toBe([]);
});

it('adds up two invoices in the same currency into one entry', function () {
    $booking = owedBooking($this->contact);
    issue($booking, 45000);
    issue($booking, 15000);

    $owed = $this->service->for($this->contact->load('bookings.invoices.payments.booking'));

    expect($owed)->toHaveCount(1)
        ->and($owed[0]->amount->minor)->toBe(60000)
        ->and($owed[0]->amount->currency)->toBe('GBP');
});

// Two bookings, two currencies. Schema section 8 forbids summing across them
// and this is the case that proves the service does not.
it('keeps two currencies apart even on the same contact', function () {
    issue(owedBooking($this->contact, 'GBP'), 45000);
    issue(owedBooking($this->contact, 'EUR'), 60000);

    $owed = collect($this->service->for($this->contact->load('bookings.invoices.payments.booking')))
        ->keyBy(fn ($amount) => $amount->amount->currency);

    expect($owed)->toHaveCount(2)
        ->and($owed['GBP']->amount->minor)->toBe(45000)
        ->and($owed['GBP']->isAccountCurrency)->toBeTrue()
        ->and($owed['EUR']->amount->minor)->toBe(60000)
        ->and($owed['EUR']->isAccountCurrency)->toBeFalse();
});

it('leaves out a currency that has been settled, rather than returning a zero', function () {
    $booking = owedBooking($this->contact);
    $invoice = issue($booking, 45000);
    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'booking_id' => $booking->id,
        'amount_minor' => 45000,
    ]);

    expect($this->service->for($this->contact->load('bookings.invoices.payments.booking')))->toBe([]);
});

// A contact who has overpaid is not owed money by the artist in any sense this
// screen means, so the entry goes rather than coming back negative.
it('leaves out a currency that has been overpaid', function () {
    $booking = owedBooking($this->contact);
    $invoice = issue($booking, 45000);
    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'booking_id' => $booking->id,
        'amount_minor' => 50000,
    ]);

    expect($this->service->for($this->contact->load('bookings.invoices.payments.booking')))->toBe([]);
});

it('marks the whole currency overdue when any one of its invoices is', function () {
    $booking = owedBooking($this->contact);
    issue($booking, 45000, ['balance_due_on' => today()->addDays(30)]);
    issue($booking, 15000, ['balance_due_on' => today()->subDays(2)]);

    $owed = $this->service->for($this->contact->load('bookings.invoices.payments.booking'));

    expect($owed)->toHaveCount(1)
        // The row shows one pill, so any one invoice being late makes the
        // amount a late amount.
        ->and($owed[0]->isOverdue)->toBeTrue()
        ->and($owed[0]->amount->minor)->toBe(60000);
});

/**
 * The reason isOverdue() takes a day rather than asking for one.
 *
 * APP_TIMEZONE is UTC. At half past eleven on a British summer evening it is
 * already tomorrow in UTC, so an invoice due today would read as overdue while
 * the artist looking at the screen is still on the day it is due. The window is
 * an hour a day and no test running at noon would ever see it, which is exactly
 * why it is pinned here.
 */
it('judges overdue by the artist\'s day rather than the application\'s', function () {
    $this->account->forceFill(['timezone' => 'Europe/London'])->save();

    $booking = owedBooking($this->contact);
    $invoice = issue($booking, 45000, ['balance_due_on' => '2027-06-14']);
    $contact = $this->contact->load('bookings.invoices.payments.booking');

    // 23:30 in London on the day it is due, which is 22:30 UTC. Both agree.
    $this->travelTo(CarbonImmutable::parse('2027-06-14 22:30:00', 'UTC'));
    expect($this->service->for($contact)[0]->isOverdue)->toBeFalse();

    // 23:30 UTC is 00:30 in London: still the 14th in UTC, already the 15th for
    // the artist. Neither is overdue yet, because the balance is due ON the
    // 14th and overdue only after it.
    $this->travelTo(CarbonImmutable::parse('2027-06-14 23:30:00', 'UTC'));
    expect($this->service->for($contact)[0]->isOverdue)->toBeTrue();

    // The UTC answer at that same instant, which is what the service would have
    // said before this was fixed. Paired with the assertion above so the
    // difference is what is being asserted, not merely the outcome.
    expect($invoice->isOverdue(CarbonImmutable::today('UTC')))->toBeFalse();

    $this->travelBack();
});

it('ignores a draft and a void invoice', function () {
    $booking = owedBooking($this->contact);

    Invoice::factory()->create(['booking_id' => $booking->id, 'total_minor' => 45000]);
    issue($booking, 15000)->forceFill(['status' => 'void', 'voided_at' => now()])->save();

    expect($this->service->for($this->contact->load('bookings.invoices.payments.booking')))->toBe([]);

    // Paired with the assertion above: an issued, unvoided invoice on the same
    // booking has to come back, or "the other two are ignored" is passing
    // because nothing was ever counted.
    issue($booking, 20000);

    expect($this->service->for($this->contact->fresh()->load('bookings.invoices.payments.booking')))
        ->toHaveCount(1);
});

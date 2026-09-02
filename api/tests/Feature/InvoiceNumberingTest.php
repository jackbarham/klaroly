<?php

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\Event;
use App\Models\Invoice;
use App\Services\InvoiceNumbering;

beforeEach(function () {
    $this->account = actingForAccount(['invoice_prefix' => 'INV']);
    $this->numbering = app(InvoiceNumbering::class);
});

function draftInvoice(): Invoice
{
    $booking = Booking::factory()->confirmed()->create();
    Event::factory()->main()->on(today()->addMonths(3)->toDateString())->create(['booking_id' => $booking->id]);
    BookingLine::factory()->create(['booking_id' => $booking->id, 'description' => 'Bride', 'unit_price_minor' => 25000]);
    BookingLine::factory()->create(['booking_id' => $booking->id, 'description' => 'Bridesmaid', 'quantity' => 2, 'unit_price_minor' => 6500]);

    return Invoice::factory()->create(['booking_id' => $booking->id, 'lines' => [], 'subtotal_minor' => 0, 'total_minor' => 0]);
}

it('numbers issued invoices in sequence with no gaps', function () {
    $first = $this->numbering->issue(draftInvoice());
    $second = $this->numbering->issue(draftInvoice());
    $third = $this->numbering->issue(draftInvoice());

    expect($first->number)->toBe('INV-0001')
        ->and($second->number)->toBe('INV-0002')
        ->and($third->number)->toBe('INV-0003')
        ->and([$first->sequence, $second->sequence, $third->sequence])->toBe([1, 2, 3])
        ->and($this->account->settings->fresh()->next_invoice_number)->toBe(4);
});

it('leaves a draft without a number', function () {
    $draft = draftInvoice();

    expect($draft->status)->toBe(InvoiceStatus::Draft)
        ->and($draft->number)->toBeNull()
        ->and($draft->sequence)->toBeNull()
        ->and($draft->issued_on)->toBeNull();
});

it('snapshots the price and payment instructions at issue', function () {
    $this->account->settings->update(['payment_instructions' => 'Pay by bank transfer.']);

    $invoice = $this->numbering->issue(draftInvoice());

    expect($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->issued_on->toDateString())->toBe(today()->toDateString())
        ->and($invoice->lines)->toHaveCount(2)
        ->and($invoice->subtotal_minor->minor)->toBe(38000)
        ->and($invoice->total_minor->minor)->toBe(38000)
        ->and($invoice->deposit_minor->minor)->toBe(9500)
        ->and($invoice->payment_instructions)->toBe('Pay by bank transfer.')
        ->and($invoice->deposit_due_on->toDateString())->toBe(today()->addDays(7)->toDateString())
        ->and($invoice->balance_due_on->toDateString())->toBe(today()->addMonths(3)->subDays(28)->toDateString());
});

it('will not issue an invoice twice', function () {
    $invoice = $this->numbering->issue(draftInvoice());

    expect(fn () => $this->numbering->issue($invoice))->toThrow(RuntimeException::class);
});

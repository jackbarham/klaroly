<?php

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;

beforeEach(function () {
    actingForAccount();
    $this->booking = Booking::factory()->confirmed()->create();
    $this->invoice = Invoice::factory()->issued()->create([
        'booking_id' => $this->booking->id,
        'total_minor' => 45000,
        'deposit_minor' => 11250,
    ]);
});

function pay(Invoice $invoice, int $amountMinor, ?string $note = null): Payment
{
    return Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'booking_id' => $invoice->booking_id,
        'amount_minor' => $amountMinor,
        'note' => $note,
    ]);
}

it('starts with nothing paid and everything outstanding', function () {
    expect($this->invoice->paidMinor())->toBe(0)
        ->and($this->invoice->outstandingMinor())->toBe(45000)
        ->and($this->invoice->depositCovered())->toBeFalse()
        ->and($this->invoice->isPaid())->toBeFalse();
});

it('marks the deposit covered once payments reach it', function () {
    pay($this->invoice, 11250);

    expect($this->invoice->depositCovered())->toBeTrue()
        ->and($this->invoice->outstandingMinor())->toBe(33750)
        ->and($this->invoice->isPaid())->toBeFalse();
});

it('is paid when payments reach the total', function () {
    pay($this->invoice, 11250);
    pay($this->invoice, 33750);

    expect($this->invoice->isPaid())->toBeTrue()
        ->and($this->invoice->outstandingMinor())->toBe(0)
        ->and($this->invoice->outstanding()->isZero())->toBeTrue();
});

it('reduces the paid figure by a negative payment', function () {
    pay($this->invoice, 11250);
    pay($this->invoice, 11250);
    $refund = pay($this->invoice, -11250, 'Paid twice, refunded.');

    expect($refund->isRefund())->toBeTrue()
        ->and($this->invoice->paidMinor())->toBe(11250)
        ->and($this->invoice->outstandingMinor())->toBe(33750);
});

it('is overdue only when owing past a due date and not snoozed', function () {
    $this->invoice->forceFill(['balance_due_on' => today()->subDay()])->save();
    expect($this->invoice->isOverdue())->toBeTrue();

    $this->invoice->forceFill(['reminders_snoozed_until' => today()->addWeek()])->save();
    expect($this->invoice->isOverdue())->toBeFalse();

    $this->invoice->forceFill(['reminders_snoozed_until' => null])->save();
    pay($this->invoice, 45000);
    expect($this->invoice->isOverdue())->toBeFalse();
});

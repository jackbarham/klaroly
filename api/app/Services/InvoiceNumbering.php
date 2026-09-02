<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\AccountSettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Issues a draft invoice: assigns the next number for the account, snapshots
 * the price and the payment instructions, and marks it issued. The
 * account_settings row is locked for the duration so two invoices issued at
 * once can never share a number, and drafts never have a number so there are
 * no gaps.
 */
class InvoiceNumbering
{
    public function __construct(private readonly BookingPricing $pricing) {}

    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new RuntimeException('Only a draft invoice can be issued.');
        }

        return DB::transaction(function () use ($invoice) {
            $settings = AccountSettings::query()
                ->where('account_id', $invoice->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            $booking = $invoice->booking;
            $sequence = $settings->next_invoice_number;
            $today = today();

            $mainEventDate = $booking->mainEvent?->event_date;

            $invoice->forceFill([
                'status' => InvoiceStatus::Issued,
                'sequence' => $sequence,
                'number' => $this->format($settings->invoice_prefix, $sequence),
                'issued_on' => $today,
                'lines' => $this->pricing->lineSnapshot($booking),
                'subtotal_minor' => $this->pricing->subtotal($booking),
                'discount_minor' => $this->pricing->discount($booking),
                'total_minor' => $this->pricing->total($booking),
                'deposit_minor' => $this->pricing->deposit($booking),
                'payment_instructions' => $settings->payment_instructions,
                'deposit_due_on' => $invoice->deposit_due_on ?? $today->addDays($settings->deposit_due_days),
                'balance_due_on' => $invoice->balance_due_on
                    ?? $mainEventDate?->subDays($settings->balance_due_days_before),
            ]);

            $invoice->save();

            $settings->next_invoice_number = $sequence + 1;
            $settings->save();

            return $invoice;
        });
    }

    /**
     * Prefix, hyphen, four-digit zero-padded sequence: INV-0042. Sequences
     * past 9999 simply grow to five digits.
     */
    public function format(string $prefix, int $sequence): string
    {
        return sprintf('%s-%04d', $prefix, $sequence);
    }
}

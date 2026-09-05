<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToAccount;
use App\Support\Money;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An invoice on a booking. Numbered at issue by App\Services\InvoiceNumbering.
 * Paid state is never stored: it is derived from payments here.
 */
#[Fillable([
    'account_id', 'booking_id', 'status', 'sequence', 'number', 'currency', 'issued_on', 'lines', 'subtotal_minor',
    'discount_minor', 'total_minor', 'deposit_minor', 'deposit_due_on', 'balance_due_on', 'payment_instructions',
    'notes', 'reminders_snoozed_until', 'pdf_path', 'voided_at', 'void_reason', 'created_by_user_id',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'lines' => 'array',
            'issued_on' => 'immutable_date',
            'subtotal_minor' => MoneyCast::class,
            'discount_minor' => MoneyCast::class,
            'total_minor' => MoneyCast::class,
            'deposit_minor' => MoneyCast::class,
            'deposit_due_on' => 'immutable_date',
            'balance_due_on' => 'immutable_date',
            'reminders_snoozed_until' => 'immutable_date',
            'voided_at' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isIssued(): bool
    {
        return $this->status === InvoiceStatus::Issued;
    }

    public function isVoid(): bool
    {
        return $this->status === InvoiceStatus::Void;
    }

    /**
     * The sum of every payment, refunds included, in minor units.
     */
    public function paidMinor(): int
    {
        return (int) $this->payments()->sum('amount_minor');
    }

    public function paid(): Money
    {
        return new Money($this->paidMinor(), $this->currency);
    }

    public function outstandingMinor(): int
    {
        return $this->total_minor->minor - $this->paidMinor();
    }

    public function outstanding(): Money
    {
        return new Money($this->outstandingMinor(), $this->currency);
    }

    public function depositCovered(): bool
    {
        return $this->paidMinor() >= $this->deposit_minor->minor;
    }

    public function isPaid(): bool
    {
        return $this->isIssued() && $this->outstandingMinor() <= 0;
    }

    /**
     * Overdue when issued, still owing, past a due date, and not snoozed.
     * The deposit due date counts only while the deposit is not yet covered.
     */
    public function isOverdue(): bool
    {
        if (! $this->isIssued() || $this->outstandingMinor() <= 0) {
            return false;
        }

        if ($this->reminders_snoozed_until !== null && $this->reminders_snoozed_until->isFuture()) {
            return false;
        }

        $today = today();

        if ($this->balance_due_on !== null && $this->balance_due_on->lessThan($today)) {
            return true;
        }

        return ! $this->depositCovered()
            && $this->deposit_due_on !== null
            && $this->deposit_due_on->lessThan($today);
    }
}

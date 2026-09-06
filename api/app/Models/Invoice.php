<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToAccount;
use App\Support\Money;
use Carbon\CarbonImmutable;
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
     *
     * It reads the loaded relation when there is one, and only falls back to
     * asking the database when there is not. That is not an optimisation, it
     * is the difference between a list endpoint working and not: this method is
     * called two or three times per invoice by outstandingMinor(),
     * depositCovered() and isOverdue(), and the query form ran every one of
     * those separately even when the caller had eager loaded payments
     * specifically to avoid it. GET /api/events has been paying that cost since
     * it was written.
     */
    public function paidMinor(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->sum(fn (Payment $payment) => $payment->amount_minor->minor);
        }

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
     *
     * `$today` is the day to judge against, and callers that know whose day it
     * is should pass it. Left out it is the application's day, which is UTC
     * (APP_TIMEZONE), and for the last hour of a British summer evening that is
     * already tomorrow: an invoice due today would read as overdue while the
     * artist looking at it is still on the day it is due. A date comparison
     * belongs in the timezone the date was written in, and accounts carry one.
     */
    public function isOverdue(?CarbonImmutable $today = null): bool
    {
        return $this->isPastDue($today) && ! $this->isSnoozed($today);
    }

    /**
     * Past a due date with money still on it, whatever the artist has told the
     * app about chasing it.
     *
     * **The date question on its own, which is a different question from
     * isOverdue().** A snooze suppresses the chasing and not the fact: an
     * invoice a fortnight late is a fortnight late whether or not the artist
     * has asked to stop being reminded. The home screen's outstanding figure
     * needs this one, because counting snoozed money as merely "due" would
     * report late money as if it were not.
     *
     * The deposit due date counts only while the deposit is not yet covered.
     */
    public function isPastDue(?CarbonImmutable $today = null): bool
    {
        if (! $this->isIssued() || $this->outstandingMinor() <= 0) {
            return false;
        }

        $today ??= CarbonImmutable::today();

        if ($this->balance_due_on !== null && $this->balance_due_on->lessThan($today)) {
            return true;
        }

        return ! $this->depositCovered()
            && $this->deposit_due_on !== null
            && $this->deposit_due_on->lessThan($today);
    }

    /**
     * Whether the artist has asked to stop being reminded about this one, and
     * the pause has not run out (decision 27).
     */
    public function isSnoozed(?CarbonImmutable $today = null): bool
    {
        $today ??= CarbonImmutable::today();

        return $this->reminders_snoozed_until !== null
            && $this->reminders_snoozed_until->greaterThan($today);
    }

    /**
     * Past its BALANCE due date, which is the half of isPastDue() the owed
     * headline is about.
     *
     * Decision 27's figure is money earned and not collected, and an unpaid
     * deposit on a wedding next summer is neither. App\Services\WaitingOnResolver
     * draws the same line between its balance and deposit branches.
     */
    public function balanceIsPastDue(?CarbonImmutable $today = null): bool
    {
        if (! $this->isIssued() || $this->outstandingMinor() <= 0) {
            return false;
        }

        $today ??= CarbonImmutable::today();

        return $this->balance_due_on !== null && $this->balance_due_on->lessThan($today);
    }
}

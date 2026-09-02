<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment against an invoice. Negative amounts are refunds. Deleted outright
 * on a misclick; there is no reversal row.
 */
#[Fillable(['account_id', 'booking_id', 'invoice_id', 'amount_minor', 'paid_on', 'method', 'reference', 'note', 'external_id', 'recorded_by_user_id'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => MoneyCast::class,
            'paid_on' => 'immutable_date',
            'method' => PaymentMethod::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function isRefund(): bool
    {
        return $this->amount_minor->isNegative();
    }
}

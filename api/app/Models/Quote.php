<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PricingMode;
use App\Enums\QuoteSentVia;
use App\Enums\QuoteStatus;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable snapshot of a quote at the moment it reached the client, with
 * an outcome. Editing the booking afterwards never touches this row.
 */
#[Fillable([
    'account_id', 'booking_id', 'number', 'currency', 'pricing_mode', 'lines', 'subtotal_minor', 'discount_minor',
    'total_minor', 'deposit_minor', 'rendered_text', 'status', 'sent_at', 'sent_via', 'responded_at', 'valid_until',
    'created_by_user_id',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_mode' => PricingMode::class,
            'lines' => 'array',
            'subtotal_minor' => MoneyCast::class,
            'discount_minor' => MoneyCast::class,
            'total_minor' => MoneyCast::class,
            'deposit_minor' => MoneyCast::class,
            'status' => QuoteStatus::class,
            'sent_via' => QuoteSentVia::class,
            'sent_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'valid_until' => 'immutable_date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\LineKind;
use App\Models\Concerns\BelongsToAccount;
use App\Support\Money;
use Database\Factories\BookingLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of the live, editable price on a booking. The description and unit
 * price are snapshots taken when the line was added.
 */
#[Fillable(['account_id', 'booking_id', 'service_id', 'kind', 'description', 'quantity', 'unit_price_minor', 'sort_order'])]
class BookingLine extends Model
{
    /** @use HasFactory<BookingLineFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LineKind::class,
            'unit_price_minor' => MoneyCast::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Quantity times unit price. Computed, never stored.
     */
    public function total(): Money
    {
        return $this->unit_price_minor->multiply($this->quantity);
    }
}

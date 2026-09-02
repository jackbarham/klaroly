<?php

namespace App\Models;

use App\Enums\BookingContactRole;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\BookingContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone on the day who is not the paying contact.
 */
#[Fillable(['account_id', 'booking_id', 'role', 'name', 'email', 'phone', 'note'])]
class BookingContact extends Model
{
    /** @use HasFactory<BookingContactFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => BookingContactRole::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}

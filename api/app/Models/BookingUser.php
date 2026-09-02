<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Database\Factories\BookingUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A collaborator on a booking. Permissions live on account_user; this row
 * only answers "is this person on this job". Empty in the first build.
 */
#[Fillable(['account_id', 'booking_id', 'user_id', 'added_by_user_id'])]
class BookingUser extends Model
{
    /** @use HasFactory<BookingUserFactory> */
    use BelongsToAccount, HasFactory;

    protected $table = 'booking_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }
}

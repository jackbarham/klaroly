<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The person who books and pays. Reusable across bookings when the same
 * family books again. Never a marketing list.
 */
#[Fillable(['account_id', 'user_id', 'first_name', 'last_name', 'email', 'phone', 'address_line_1', 'address_line_2', 'city', 'postcode', 'country'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use BelongsToAccount, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }
}

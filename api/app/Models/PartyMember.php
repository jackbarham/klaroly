<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Database\Factories\PartyMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone being served on the day, with a service from the rate card. The
 * service name is snapshotted so a deleted rate card row leaves the list
 * readable.
 */
#[Fillable(['account_id', 'booking_id', 'event_id', 'name', 'service_id', 'service_name', 'sort_order'])]
class PartyMember extends Model
{
    /** @use HasFactory<PartyMemberFactory> */
    use BelongsToAccount, HasFactory;

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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

<?php

namespace App\Models;

use App\Enums\EventType;
use App\Enums\LocationType;
use App\Models\Concerns\BelongsToAccount;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Anything on a booking that touches a date. Stored as local wall clock plus
 * an IANA zone; see the comment at the top of the events migration.
 */
#[Fillable([
    'account_id', 'booking_id', 'type', 'label', 'event_date', 'start_time', 'end_time', 'ready_by_time', 'timezone',
    'location_type', 'address_line_1', 'address_line_2', 'city', 'postcode', 'country', 'latitude', 'longitude',
    'venue_name', 'venue_address', 'travel_distance_m', 'travel_duration_s', 'travel_estimated_at', 'sort_order',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'location_type' => LocationType::class,
            'event_date' => 'immutable_date',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'travel_estimated_at' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function partyMembers(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    /**
     * The diary's total order: date, then start time with nulls last, then id.
     * An event with no call time belongs at the end of its day rather than the
     * start of it, and without the final id two events at the same time could
     * swap between requests. Postgres sorts nulls last on an ascending column
     * by default, but saying so is what keeps this true if the column or the
     * database ever changes. GET /api/events renders in exactly this sequence
     * and must not have to sort again, and the home screen's Next up block
     * reads the same order.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    #[Scope]
    protected function inDiaryOrder(Builder $query): Builder
    {
        return $query
            ->orderBy('event_date')
            ->orderByRaw('start_time asc nulls last')
            ->orderBy('id');
    }

    public function isMain(): bool
    {
        return $this->type === EventType::Main;
    }

    /**
     * The event's start as an instant in the event's own zone. With no call
     * time recorded, this is midnight on the day.
     */
    public function startsAt(): CarbonImmutable
    {
        return $this->atLocalTime($this->start_time ?? '00:00:00');
    }

    public function endsAt(): ?CarbonImmutable
    {
        return $this->end_time === null ? null : $this->atLocalTime($this->end_time);
    }

    public function readyBy(): ?CarbonImmutable
    {
        return $this->ready_by_time === null ? null : $this->atLocalTime($this->ready_by_time);
    }

    private function atLocalTime(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($this->event_date->format('Y-m-d').' '.$time, $this->timezone);
    }
}

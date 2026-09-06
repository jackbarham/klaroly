<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\AgreementStatus;
use App\Enums\BookingSource;
use App\Enums\BookingStage;
use App\Enums\DiscountType;
use App\Enums\EventType;
use App\Enums\LostReason;
use App\Enums\PhotoConsent;
use App\Enums\PricingMode;
use App\Models\Concerns\BelongsToAccount;
use App\Support\CurrentAccount;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row from first enquiry to closed. Totals are never stored here; see
 * App\Services\BookingPricing.
 */
#[Fillable([
    'account_id', 'contact_id', 'stage', 'source', 'source_booking_id', 'enquiry_message', 'lost_reason', 'lost_at',
    'converted_at', 'confirmed_at', 'cancelled_at', 'cancellation_reason', 'hold_expires_at', 'last_touched_at',
    'currency', 'pricing_mode', 'fixed_price_minor', 'fixed_price_description', 'discount_type', 'discount_value',
    'discount_reason', 'deposit_override_minor', 'deposit_override_percent', 'photo_consent',
    'photo_consent_recorded_at', 'gallery_url', 'gallery_received_on', 'access_pin', 'access_pin_changed_at',
    'feature_overrides', 'created_by_user_id',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToAccount, HasFactory, SoftDeletes;

    /**
     * The stages that make a row an enquiry rather than a booking.
     *
     * @var array<int, BookingStage>
     */
    public const ENQUIRY_STAGES = [
        BookingStage::New,
        BookingStage::InConversation,
        BookingStage::Possible,
        BookingStage::Quoted,
    ];

    /**
     * The stages GET /api/enquiries returns and GET /api/enquiries/{booking}
     * will find: the four live ones plus the archive, which the screen shows
     * behind a switch.
     *
     * @var array<int, BookingStage>
     */
    public const LISTED_STAGES = [...self::ENQUIRY_STAGES, BookingStage::Lost];

    /**
     * The stages PATCH /api/enquiries/{booking} may move a record to, and the
     * stages it accepts a record at.
     *
     * The same six on both sides, and the whole matrix: any of them to any
     * other. **It is deliberately not a state machine** (decision 235). The
     * artist decides, the app suggests, and an artist putting a quoted enquiry
     * back to In conversation has a reason this route does not need to know.
     * Provisional is here and not in LISTED_STAGES above, which is the one
     * asymmetry: converting is reversible until something is signed (business
     * logic 5.3), so the write has to accept a provisional record even though
     * the list no longer shows one.
     *
     * Confirmed and beyond are absent, because changing the stage of a signed
     * job through a route built for a list of maybes is a downgrade. That is a
     * 422 rather than a 403: the caller is allowed to be here and the target is
     * wrong.
     *
     * @var array<int, BookingStage>
     */
    public const SETTABLE_STAGES = [...self::LISTED_STAGES, BookingStage::Provisional];

    /**
     * @var array<int, BookingStage>
     */
    public const BOOKING_STAGES = [
        BookingStage::Provisional,
        BookingStage::Confirmed,
        BookingStage::Completed,
        BookingStage::Closed,
        BookingStage::Cancelled,
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            // The currency defaults from the account in the model, never in
            // the schema, so a job abroad can be priced in another currency.
            if (empty($booking->currency)) {
                $account = app(CurrentAccount::class)->get() ?? Account::find($booking->account_id);
                $booking->currency = $account?->currency ?? 'GBP';
            }

            if ($booking->last_touched_at === null) {
                $booking->last_touched_at = now();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => BookingStage::class,
            'source' => BookingSource::class,
            'lost_reason' => LostReason::class,
            'pricing_mode' => PricingMode::class,
            'discount_type' => DiscountType::class,
            'photo_consent' => PhotoConsent::class,
            'fixed_price_minor' => MoneyCast::class,
            'deposit_override_minor' => MoneyCast::class,
            'feature_overrides' => 'array',
            'access_pin' => 'encrypted',
            'hold_expires_at' => 'immutable_date',
            'gallery_received_on' => 'immutable_date',
            'lost_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'last_touched_at' => 'immutable_datetime',
            'photo_consent_recorded_at' => 'immutable_datetime',
            'access_pin_changed_at' => 'immutable_datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sourceBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'source_booking_id');
    }

    public function capturedBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'source_booking_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('event_date')->orderBy('sort_order');
    }

    public function mainEvent(): HasOne
    {
        return $this->hasOne(Event::class)->where('type', EventType::Main->value);
    }

    public function partyMembers(): HasMany
    {
        return $this->hasMany(PartyMember::class)->orderBy('sort_order');
    }

    public function bookingContacts(): HasMany
    {
        return $this->hasMany(BookingContact::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BookingLine::class)->orderBy('sort_order');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->orderBy('number');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class)->orderBy('version');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)->latest();
    }

    public function messageTemplates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'booking_user')->withTimestamps();
    }

    public function isEnquiry(): bool
    {
        return in_array($this->stage, self::ENQUIRY_STAGES, true);
    }

    public function isBooking(): bool
    {
        return in_array($this->stage, self::BOOKING_STAGES, true);
    }

    /**
     * The highest-version signed agreement, or null when none is signed.
     */
    public function agreementInForce(): ?Agreement
    {
        return $this->agreements()
            ->where('status', AgreementStatus::Signed->value)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Record that something happened on this booking, and save.
     *
     * **Every write path that touches a booking, or anything belonging to one,
     * calls this.** `last_touched_at` is what the enquiries list is ordered by
     * and what the cold-enquiry branch of App\Services\WaitingOnResolver
     * reads, so a writer that does not call it is not a bug in itself: it is a
     * bug in the enquiries list and in the Home attention block, arriving
     * somewhere neither of them can see. Notes, messages, price changes and
     * the intake form are all still to come, and each of them is somebody
     * forgetting.
     *
     * It saves rather than only setting the column, which is what Laravel's
     * own touch() does. So a caller fills what it is changing and calls this,
     * and one write goes to the database:
     *
     *     $booking->fill([...])->touchActivity();
     *
     * forceFill, because last_touched_at is not something a request may set.
     */
    public function touchActivity(): bool
    {
        return $this->forceFill(['last_touched_at' => now()])->save();
    }

    /**
     * Whether the hold on this date has lapsed. Only meaningful while the
     * booking is at a stage that holds one.
     *
     * **The last day of a hold is still a hold.** The column is a date and the
     * cast is immutable_date, so isPast() was true from one second after
     * midnight on the expiry day itself, making a fourteen-day hold dead on day
     * fourteen rather than after it. Every wording around this feature is
     * inclusive: "held until 4 October" means the fourth is covered.
     *
     * `$today` is the day to judge against, and callers that know whose day it
     * is should pass it, exactly as App\Models\Invoice::isOverdue() takes one.
     * Left out it is the application's day, which is UTC (APP_TIMEZONE), and a
     * date comparison belongs in the timezone the date was written in. This was
     * the last comparison of a stored date against the present still asking
     * UTC.
     *
     * Neither defect could be reached by any existing test, because every
     * fixture set the hold a day or more either side of today and none of them
     * touched the boundary. That is decision 197's lesson again: a test that
     * never touches the edge is documentation rather than a guard.
     */
    public function holdHasExpired(?CarbonImmutable $today = null): bool
    {
        if ($this->hold_expires_at === null) {
            return false;
        }

        return $this->hold_expires_at->lessThan($today ?? CarbonImmutable::today());
    }
}

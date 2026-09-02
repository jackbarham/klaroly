<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\AgreementStatus;
use App\Enums\BookingSource;
use App\Enums\BookingStage;
use App\Enums\DiscountType;
use App\Enums\EventType;
use App\Enums\PhotoConsent;
use App\Enums\PricingMode;
use App\Models\Concerns\BelongsToAccount;
use App\Support\CurrentAccount;
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
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
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
     * Whether the provisional hold has lapsed. Only meaningful while the
     * booking is provisional.
     */
    public function holdHasExpired(): bool
    {
        return $this->hold_expires_at !== null && $this->hold_expires_at->isPast();
    }
}

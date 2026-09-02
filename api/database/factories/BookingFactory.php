<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStage;
use App\Enums\DiscountType;
use App\Enums\PricingMode;
use App\Models\Booking;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'stage' => BookingStage::New,
            'source' => BookingSource::Manual,
            'last_touched_at' => now(),
            'currency' => 'GBP',
            'pricing_mode' => PricingMode::Itemised,
            'feature_overrides' => [],
        ];
    }

    public function stage(BookingStage $stage): static
    {
        return $this->state(fn (array $attributes) => ['stage' => $stage]);
    }

    public function inConversation(): static
    {
        return $this->stage(BookingStage::InConversation);
    }

    public function possible(): static
    {
        return $this->stage(BookingStage::Possible);
    }

    public function quoted(): static
    {
        return $this->stage(BookingStage::Quoted);
    }

    public function provisional(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => BookingStage::Provisional,
            'converted_at' => now()->subDays(3),
            'hold_expires_at' => today()->addDays(11),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => BookingStage::Confirmed,
            'converted_at' => now()->subWeeks(3),
            'confirmed_at' => now()->subWeeks(2),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => BookingStage::Completed,
            'converted_at' => now()->subMonths(6),
            'confirmed_at' => now()->subMonths(5),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => BookingStage::Closed,
            'converted_at' => now()->subMonths(8),
            'confirmed_at' => now()->subMonths(7),
        ]);
    }

    public function lost(string $reason = 'went_elsewhere'): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => BookingStage::Lost,
            'lost_reason' => $reason,
            'lost_at' => now()->subWeeks(2),
        ]);
    }

    public function cancelled(string $reason = 'Occasion postponed.'): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => BookingStage::Cancelled,
            'cancelled_at' => now()->subWeek(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function fixedPrice(int $priceMinor, string $description = 'Full day package'): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PricingMode::Fixed,
            'fixed_price_minor' => $priceMinor,
            'fixed_price_description' => $description,
        ]);
    }

    public function withAmountDiscount(int $amountMinor, string $reason = 'Returning client'): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::Amount,
            'discount_value' => $amountMinor,
            'discount_reason' => $reason,
        ]);
    }

    public function withPercentDiscount(int $percent, string $reason = 'Off-season'): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::Percent,
            'discount_value' => $percent,
            'discount_reason' => $reason,
        ]);
    }
}

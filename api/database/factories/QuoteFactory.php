<?php

namespace Database\Factories;

use App\Enums\PricingMode;
use App\Enums\QuoteSentVia;
use App\Enums\QuoteStatus;
use App\Models\Booking;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'number' => 1,
            'currency' => 'GBP',
            'pricing_mode' => PricingMode::Itemised,
            'lines' => [],
            'subtotal_minor' => 45000,
            'discount_minor' => 0,
            'total_minor' => 45000,
            'deposit_minor' => 11250,
            'rendered_text' => 'Quote text',
            'status' => QuoteStatus::Sent,
            'sent_at' => now()->subDays(5),
            'sent_via' => QuoteSentVia::Copy,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Accepted,
            'responded_at' => now()->subDays(2),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Declined,
            'responded_at' => now()->subDays(2),
        ]);
    }
}

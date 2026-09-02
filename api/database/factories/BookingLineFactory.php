<?php

namespace Database\Factories;

use App\Enums\LineKind;
use App\Models\Booking;
use App\Models\BookingLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingLine>
 */
class BookingLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'kind' => LineKind::Service,
            'description' => 'Bridesmaid',
            'quantity' => 1,
            'unit_price_minor' => 6500,
            'sort_order' => 0,
        ];
    }

    public function custom(string $description, int $unitPriceMinor): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => LineKind::Custom,
            'description' => $description,
            'unit_price_minor' => $unitPriceMinor,
        ]);
    }
}

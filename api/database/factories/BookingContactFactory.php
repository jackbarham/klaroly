<?php

namespace Database\Factories;

use App\Enums\BookingContactRole;
use App\Models\Booking;
use App\Models\BookingContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingContact>
 */
class BookingContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'role' => BookingContactRole::Partner,
            'name' => fake()->firstName().' '.fake()->lastName(),
            'phone' => '07'.fake()->numerify('### ######'),
        ];
    }
}

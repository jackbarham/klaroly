<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingUser>
 */
class BookingUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'user_id' => User::factory(),
        ];
    }
}

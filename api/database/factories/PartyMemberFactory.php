<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\PartyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyMember>
 */
class PartyMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'name' => fake()->firstName(),
            'service_name' => 'Bridesmaid',
            'sort_order' => 0,
        ];
    }
}

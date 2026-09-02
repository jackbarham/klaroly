<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'body' => fake()->randomElement([
                'Prefers a natural look, nothing too heavy.',
                'Sensitive skin, patch test at the trial.',
                'Mum would like a light touch-up only.',
                'Parking is behind the venue, ask for the events team.',
                'Running order confirmed with the photographer.',
            ]),
        ];
    }
}

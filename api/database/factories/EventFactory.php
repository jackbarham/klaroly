<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Enums\LocationType;
use App\Models\Booking;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'type' => EventType::Main,
            'event_date' => today()->addMonths(fake()->numberBetween(1, 10)),
            'start_time' => '07:30:00',
            'ready_by_time' => '12:00:00',
            'timezone' => 'Europe/London',
            'location_type' => LocationType::Venue,
            'country' => 'GB',
            'sort_order' => 0,
        ];
    }

    public function main(): static
    {
        return $this->state(fn (array $attributes) => ['type' => EventType::Main]);
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EventType::Trial,
            'start_time' => '10:00:00',
            'ready_by_time' => null,
            'location_type' => LocationType::Base,
            'sort_order' => -1,
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (array $attributes) => ['event_date' => $date]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\AgreementStatus;
use App\Enums\SignedMethod;
use App\Models\Agreement;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = 'Agreement text for a booking.';

        return [
            'booking_id' => Booking::factory(),
            'version' => 1,
            'status' => AgreementStatus::Draft,
            'rendered_body' => $body,
            'rendered_sha256' => Agreement::hashBody($body),
            'total_minor' => 45000,
            'deposit_minor' => 11250,
        ];
    }

    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AgreementStatus::Signed,
            'sent_at' => now()->subDays(10),
            'signed_at' => now()->subDays(8),
            'signed_method' => SignedMethod::Manual,
            'signed_name' => fake()->firstName().' '.fake()->lastName(),
            'signed_note' => 'Signed copy received by email.',
        ]);
    }
}

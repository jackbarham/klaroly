<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * A draft with no number. Use App\Services\InvoiceNumbering to issue it
     * properly; the issued() state is for tests that only need the shape.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'status' => InvoiceStatus::Draft,
            'currency' => 'GBP',
            'lines' => [],
            'subtotal_minor' => 45000,
            'discount_minor' => 0,
            'total_minor' => 45000,
            'deposit_minor' => 11250,
        ];
    }

    public function issued(int $sequence = 1, string $prefix = 'INV'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Issued,
            'sequence' => $sequence,
            'number' => sprintf('%s-%04d', $prefix, $sequence),
            'issued_on' => today()->subDays(14),
            'deposit_due_on' => today()->subDays(7),
            'balance_due_on' => today()->addDays(30),
        ]);
    }

    public function snoozedUntil(string $date): static
    {
        return $this->state(fn (array $attributes) => ['reminders_snoozed_until' => $date]);
    }
}

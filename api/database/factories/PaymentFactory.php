<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'booking_id' => fn (array $attributes) => Invoice::query()->withoutGlobalScope('account')->find($attributes['invoice_id'])->booking_id,
            'amount_minor' => 11250,
            'paid_on' => today()->subDays(3),
            'method' => PaymentMethod::BankTransfer,
            'reference' => strtoupper(fake()->lexify('??????')),
        ];
    }

    public function refund(int $amountMinor = 5000, string $note = 'Refunded after a change of plan.'): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_minor' => -abs($amountMinor),
            'note' => $note,
        ]);
    }
}

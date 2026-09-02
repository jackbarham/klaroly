<?php

namespace App\Services;

use App\Enums\DepositType;
use App\Enums\DiscountType;
use App\Enums\PricingMode;
use App\Models\Booking;
use App\Support\Money;

/**
 * The one place a booking's subtotal, discount, total and deposit are worked
 * out. Nothing here is stored; quotes and invoices snapshot the result at the
 * moment they are created.
 */
class BookingPricing
{
    /**
     * In itemised mode, the sum of every line. In fixed mode, the fixed price
     * and the lines are ignored.
     */
    public function subtotal(Booking $booking): Money
    {
        if ($booking->pricing_mode === PricingMode::Fixed) {
            return $booking->fixed_price_minor ?? Money::zero($booking->currency);
        }

        $subtotal = Money::zero($booking->currency);

        foreach ($booking->lines as $line) {
            $subtotal = $subtotal->add($line->total());
        }

        return $subtotal;
    }

    /**
     * The discount as a positive amount, never more than the subtotal.
     */
    public function discount(Booking $booking): Money
    {
        $subtotal = $this->subtotal($booking);

        if ($booking->discount_type === null || $booking->discount_value === null) {
            return Money::zero($booking->currency);
        }

        $discount = match ($booking->discount_type) {
            DiscountType::Amount => new Money($booking->discount_value, $booking->currency),
            DiscountType::Percent => $subtotal->percentage($booking->discount_value),
        };

        return $discount->min($subtotal);
    }

    public function total(Booking $booking): Money
    {
        return $this->subtotal($booking)->subtract($this->discount($booking));
    }

    /**
     * The deposit: the booking's own override if it has one, otherwise the
     * account rule. Never more than the total.
     */
    public function deposit(Booking $booking): Money
    {
        $total = $this->total($booking);

        if ($booking->deposit_override_minor !== null) {
            return $booking->deposit_override_minor->min($total);
        }

        if ($booking->deposit_override_percent !== null) {
            return $total->percentage($booking->deposit_override_percent);
        }

        $settings = $booking->account->settings;

        if ($settings === null) {
            return Money::zero($booking->currency);
        }

        $deposit = match ($settings->deposit_type) {
            DepositType::Fixed => $settings->deposit_amount_minor !== null
                ? new Money($settings->deposit_amount_minor->minor, $booking->currency)
                : Money::zero($booking->currency),
            DepositType::Percent => $total->percentage($settings->deposit_percent ?? 0),
        };

        return $deposit->min($total);
    }

    /**
     * The lines as a plain array suitable for a jsonb snapshot on a quote or
     * an invoice. In fixed mode the snapshot is the single fixed-price line.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lineSnapshot(Booking $booking): array
    {
        if ($booking->pricing_mode === PricingMode::Fixed) {
            return [[
                'kind' => 'custom',
                'description' => $booking->fixed_price_description ?? '',
                'quantity' => 1,
                'unit_price_minor' => $booking->fixed_price_minor?->minor ?? 0,
                'total_minor' => $booking->fixed_price_minor?->minor ?? 0,
            ]];
        }

        return $booking->lines->map(fn ($line) => [
            'kind' => $line->kind->value,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_price_minor' => $line->unit_price_minor->minor,
            'total_minor' => $line->total()->minor,
        ])->values()->all();
    }
}

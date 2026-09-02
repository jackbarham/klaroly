<?php

use App\Enums\DiscountType;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Services\BookingPricing;

beforeEach(function () {
    $this->account = actingForAccount(['deposit_percent' => 25]);
    $this->pricing = app(BookingPricing::class);
});

function itemisedBooking(array $attributes = []): Booking
{
    $booking = Booking::factory()->create($attributes);
    BookingLine::factory()->create(['booking_id' => $booking->id, 'description' => 'Bride', 'unit_price_minor' => 25000]);
    BookingLine::factory()->create(['booking_id' => $booking->id, 'description' => 'Bridesmaid', 'quantity' => 2, 'unit_price_minor' => 6500]);

    return $booking->fresh();
}

it('sums the lines in itemised mode', function () {
    $booking = itemisedBooking();

    expect($this->pricing->subtotal($booking)->minor)->toBe(38000)
        ->and($this->pricing->discount($booking)->isZero())->toBeTrue()
        ->and($this->pricing->total($booking)->minor)->toBe(38000)
        ->and($this->pricing->total($booking)->currency)->toBe('GBP');
});

it('ignores the lines in fixed price mode', function () {
    $booking = Booking::factory()->fixedPrice(95000)->create();
    BookingLine::factory()->create(['booking_id' => $booking->id, 'unit_price_minor' => 25000]);

    expect($this->pricing->subtotal($booking->fresh())->minor)->toBe(95000)
        ->and($this->pricing->total($booking->fresh())->minor)->toBe(95000)
        ->and($this->pricing->lineSnapshot($booking->fresh()))->toHaveCount(1);
});

it('applies an amount discount', function () {
    $booking = itemisedBooking(['discount_type' => DiscountType::Amount, 'discount_value' => 3000]);

    expect($this->pricing->discount($booking)->minor)->toBe(3000)
        ->and($this->pricing->total($booking)->minor)->toBe(35000);
});

it('applies a percentage discount', function () {
    $booking = itemisedBooking(['discount_type' => DiscountType::Percent, 'discount_value' => 10]);

    expect($this->pricing->discount($booking)->minor)->toBe(3800)
        ->and($this->pricing->total($booking)->minor)->toBe(34200);
});

it('never discounts below zero', function () {
    $booking = itemisedBooking(['discount_type' => DiscountType::Amount, 'discount_value' => 99999]);

    expect($this->pricing->total($booking)->isZero())->toBeTrue();
});

it('takes the deposit from the account rule by default', function () {
    $booking = itemisedBooking();

    expect($this->pricing->deposit($booking)->minor)->toBe(9500);
});

it('lets a booking override the deposit as an amount or a percentage', function () {
    $byAmount = itemisedBooking(['deposit_override_minor' => 10000]);
    $byPercent = itemisedBooking(['deposit_override_percent' => 50]);

    expect($this->pricing->deposit($byAmount)->minor)->toBe(10000)
        ->and($this->pricing->deposit($byPercent)->minor)->toBe(19000);
});

it('uses a fixed deposit amount when the account rule is fixed', function () {
    $this->account->settings->update(['deposit_type' => 'fixed', 'deposit_amount_minor' => 15000, 'deposit_percent' => null]);

    $booking = itemisedBooking();

    expect($this->pricing->deposit($booking)->minor)->toBe(15000);
});

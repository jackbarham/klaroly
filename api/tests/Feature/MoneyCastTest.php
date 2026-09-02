<?php

use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\Service;
use App\Support\Money;

beforeEach(function () {
    actingForAccount();
});

it('reads an integer column as a Money in the booking\'s currency', function () {
    $booking = Booking::factory()->create(['currency' => 'EUR']);
    $line = BookingLine::factory()->create(['booking_id' => $booking->id, 'unit_price_minor' => 6500]);

    $line = $line->fresh();

    expect($line->unit_price_minor)->toBeInstanceOf(Money::class)
        ->and($line->unit_price_minor->minor)->toBe(6500)
        ->and($line->unit_price_minor->currency)->toBe('EUR')
        ->and($line->getRawOriginal('unit_price_minor'))->toBe(6500)
        ->and($line->total()->minor)->toBe(6500);
});

it('writes a Money back as an integer', function () {
    $booking = Booking::factory()->create();
    $line = BookingLine::factory()->create(['booking_id' => $booking->id]);

    $line->unit_price_minor = new Money(7250, 'GBP');
    $line->save();

    expect($line->fresh()->getRawOriginal('unit_price_minor'))->toBe(7250);
});

it('accepts a plain int', function () {
    $service = Service::factory()->create(['price_minor' => 25000]);

    expect($service->fresh()->price_minor->minor)->toBe(25000)
        ->and($service->fresh()->price_minor->currency)->toBe('GBP');
});

it('refuses a float', function () {
    $booking = Booking::factory()->create();
    $line = BookingLine::factory()->make(['booking_id' => $booking->id]);

    expect(fn () => $line->unit_price_minor = 65.0)->toThrow(InvalidArgumentException::class, 'float');
});

it('formats with the currency and locale rather than a hard-coded symbol', function () {
    expect((new Money(123456, 'GBP'))->format('en-GB'))->toBe("\u{a3}1,234.56")
        ->and((new Money(-800, 'EUR'))->format('de-DE'))->toBe("-8,00\u{a0}\u{20ac}")
        ->and((new Money(5000, 'JPY'))->format('en-GB'))->toBe("JP\u{a5}5,000");
});

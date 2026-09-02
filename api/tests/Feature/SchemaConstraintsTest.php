<?php

use App\Models\Booking;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('rejects a booking stage that is not in the enum', function () {
    $account = actingForAccount();
    $booking = Booking::factory()->create();

    expect(fn () => DB::table('bookings')->insert([
        'account_id' => $account->id,
        'contact_id' => $booking->contact_id,
        'stage' => 'won',
        'last_touched_at' => now(),
        'currency' => 'GBP',
        'feature_overrides' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class, 'bookings_stage_check');
});

it('allows only one main event per booking', function () {
    actingForAccount();
    $booking = Booking::factory()->create();

    Event::factory()->main()->create(['booking_id' => $booking->id]);
    Event::factory()->trial()->create(['booking_id' => $booking->id]);

    expect(fn () => Event::factory()->main()->create(['booking_id' => $booking->id]))
        ->toThrow(QueryException::class, 'events_booking_id_main_unique');
});

it('treats user emails as unique regardless of case', function () {
    User::factory()->create(['email' => 'Ellie@Example.com']);

    expect(fn () => User::factory()->create(['email' => 'ellie@example.com']))
        ->toThrow(QueryException::class, 'users_email_lower_unique');
});

it('refuses a zero payment', function () {
    actingForAccount();
    $booking = Booking::factory()->create();
    $invoice = Invoice::factory()->issued()->create(['booking_id' => $booking->id]);

    expect(fn () => Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'booking_id' => $booking->id,
        'amount_minor' => 0,
    ]))->toThrow(QueryException::class, 'payments_amount_minor_check');
});

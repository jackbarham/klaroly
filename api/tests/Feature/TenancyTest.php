<?php

use App\Models\Account;
use App\Models\Agreement;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingLine;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\PartyMember;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Service;

it('hides one account\'s data from another through every scoped model', function () {
    $accountA = actingForAccount();
    Booking::factory()->create();

    $accountB = Account::factory()->withSettings()->create();
    currentAccount()->set($accountB);

    $booking = Booking::factory()->confirmed()->create();
    Event::factory()->main()->create(['booking_id' => $booking->id]);
    PartyMember::factory()->create(['booking_id' => $booking->id]);
    BookingContact::factory()->create(['booking_id' => $booking->id]);
    BookingLine::factory()->create(['booking_id' => $booking->id]);
    Quote::factory()->create(['booking_id' => $booking->id]);
    $invoice = Invoice::factory()->issued()->create(['booking_id' => $booking->id]);
    Payment::factory()->create(['invoice_id' => $invoice->id, 'booking_id' => $booking->id]);
    Agreement::factory()->signed()->create(['booking_id' => $booking->id]);
    Note::factory()->create(['booking_id' => $booking->id]);
    Service::factory()->create();

    currentAccount()->set($accountA);

    expect(Booking::count())->toBe(1)
        ->and(Booking::find($booking->id))->toBeNull()
        ->and(Contact::count())->toBe(1)
        ->and(Event::count())->toBe(0)
        ->and(PartyMember::count())->toBe(0)
        ->and(BookingContact::count())->toBe(0)
        ->and(BookingLine::count())->toBe(0)
        ->and(Quote::count())->toBe(0)
        ->and(Invoice::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(Agreement::count())->toBe(0)
        ->and(Note::count())->toBe(0)
        ->and(Service::count())->toBe(0);

    expect(Booking::withoutGlobalScope('account')->count())->toBe(2);
});

it('files a new row under the current account when no account_id is given', function () {
    $account = actingForAccount();

    $booking = Booking::factory()->create();

    expect($booking->account_id)->toBe($account->id)
        ->and($booking->contact->account_id)->toBe($account->id);
});

it('refuses to create a scoped row with no current account', function () {
    $account = Account::factory()->withSettings()->create();
    currentAccount()->clear();

    expect(fn () => Service::create(['name' => 'Bride', 'price_minor' => 25000]))
        ->toThrow(RuntimeException::class, 'without a current account');

    // An explicit account_id is still honoured, so seeders and jobs can be
    // deliberate about which tenant they write to.
    $service = Service::create(['account_id' => $account->id, 'name' => 'Bride', 'price_minor' => 25000]);
    expect($service->account_id)->toBe($account->id);
});

it('returns nothing from a scoped query when no account is bound', function () {
    actingForAccount();
    Booking::factory()->create();

    currentAccount()->clear();

    expect(Booking::count())->toBe(0);
});

it('refuses to answer who a user is a member of when no account is bound', function () {
    $account = Account::factory()->withSettings()->create();
    $user = createOwner([], $account);

    currentAccount()->clear();

    // The scope would fail closed and answer null, and null is read as "not
    // an owner" by every permission check. A missing tenant has to say so.
    expect(fn () => $user->currentMembership())
        ->toThrow(RuntimeException::class, 'No current account is set.');
});

it('answers null only when the user is not a member of the bound account', function () {
    $account = actingForAccount();
    $member = createOwner([], $account);
    $stranger = createOwner();

    currentAccount()->set($account);

    expect($member->currentMembership()->account_id)->toBe($account->id)
        ->and($member->currentMembership()->isOwner())->toBeTrue()
        ->and($stranger->currentMembership())->toBeNull();
});

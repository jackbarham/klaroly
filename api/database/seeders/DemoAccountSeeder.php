<?php

namespace Database\Seeders;

use App\Enums\AccountRole;
use App\Enums\AgreementStatus;
use App\Enums\BookingContactRole;
use App\Enums\BookingSource;
use App\Enums\BookingStage;
use App\Enums\DepositType;
use App\Enums\DiscountType;
use App\Enums\EventType;
use App\Enums\FeatureKey;
use App\Enums\HoldClass;
use App\Enums\InvoiceStatus;
use App\Enums\LineKind;
use App\Enums\LocationType;
use App\Enums\LostReason;
use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Enums\QuoteSentVia;
use App\Enums\QuoteStatus;
use App\Enums\ServiceAppliesTo;
use App\Enums\ServiceKind;
use App\Enums\SignedMethod;
use App\Enums\TravelCharging;
use App\Models\Account;
use App\Models\AccountSettings;
use App\Models\AccountUser;
use App\Models\Agreement;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingLine;
use App\Models\Contact;
use App\Models\ContractTemplate;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use App\Models\Note;
use App\Models\PartyMember;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingPricing;
use App\Services\InvoiceNumbering;
use App\Services\SoftHold;
use App\Support\CurrentAccount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * One realistic account that doubles as the App Store review account.
 *
 * Every person and every venue here is invented, and the venues deliberately
 * so: this account is what gets screenshotted, demonstrated and handed to
 * Apple, and naming a real wedding venue in it implies a relationship with
 * that business which does not exist. The same rule the app's own fixtures
 * were given, for the same reason.
 *
 * Towns, counties and streets are kept, because they name a place rather than
 * a business, and so is the outward half of each postcode, which names a
 * postal district. The inward half is invented, because that names a delivery
 * point and so effectively a building. Nothing depends on any of them
 * resolving: the seeder sets no travel columns, there is no geocoding
 * anywhere in the app, and travel estimates are decision 74, unused in v1.
 * When they arrive, whether the demo wants postcodes that really resolve is a
 * decision to make then rather than one inherited by accident.
 *
 * Dates are relative to today so the demo always has bookings ahead of it
 * and money owed behind it.
 */
class DemoAccountSeeder extends Seeder
{
    public const USERNAME = 'elliemarsh';

    private Account $account;

    private User $owner;

    /** @var Collection<string, Service> keyed by service name */
    private Collection $services;

    /** @var array<string, Contact> */
    private array $contacts = [];

    /** @var array<int, Booking> */
    private array $bookings = [];

    public function __construct(
        private readonly BookingPricing $pricing,
        private readonly InvoiceNumbering $numbering,
    ) {}

    public function run(): void
    {
        if (Account::query()->where('username', self::USERNAME)->exists()) {
            $this->command?->info('Demo account already seeded, skipping.');

            return;
        }

        $this->seedAccount();
        app(CurrentAccount::class)->set($this->account);

        $this->seedRateCard();
        $this->seedContacts();
        $this->seedBookings();
        $this->seedQuotes();
        $this->seedInvoices();
        $this->seedAgreements();

        app(CurrentAccount::class)->clear();
    }

    private function seedAccount(): void
    {
        $this->account = Account::create([
            'name' => 'Ellie Marsh Makeup',
            'username' => self::USERNAME,
            'vertical' => SystemDefaultsSeeder::VERTICAL,
            'country' => 'GB',
            'locale' => 'en-GB',
            'currency' => 'GBP',
            'timezone' => 'Europe/London',
            'profile_enabled' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        AccountSettings::create([
            'account_id' => $this->account->id,
            // The map a real registration writes, plus the extras this demo
            // wants switched on, named one at a time.
            //
            // It used to be every key mapped to true, which quietly made the
            // demo account a shape no signup produces. That account is what
            // gets screenshotted, shown to an artist, and handed to Apple with
            // a statement about what the app does, so the difference between
            // it and a real signup should be three lines somebody can read
            // rather than an accident.
            //
            // Intake forms are deliberately not among them: section 7.4 is
            // designed and not migrated, so switching the toggle on would
            // promise a screen that does not exist.
            'features' => [
                FeatureKey::Photos->value => true,
                FeatureKey::TravelEstimates->value => true,
                FeatureKey::FeedbackRequests->value => true,
            ] + config('features.defaults'),
            'deposit_type' => DepositType::Percent,
            'deposit_percent' => 25,
            'deposit_due_days' => 7,
            'balance_due_days_before' => 28,
            'payment_instructions' => "Bank transfer to Ellie Marsh Makeup\nSort code 04-00-04\nAccount number 12345678\nPlease use your surname and the date as the reference.",
            'invoice_prefix' => 'INV',
            'legal_name' => 'Ellie Marsh',
            'address_line_1' => '27 Ilminster Rise',
            'city' => 'Bristol',
            'postcode' => 'BS6 6XA',
            'base_postcode' => 'BS6 6XA',
            'travel_charging' => TravelCharging::PerMile,
            'travel_free_radius_miles' => 10,
            'travel_rate_per_mile_minor' => 45,
        ]);

        $this->owner = User::create([
            'name' => 'Ellie Marsh',
            'email' => 'ellie@example.com',
            'password' => Hash::make(config('demo.password')),
            'email_verified_at' => now(),
            'last_account_id' => $this->account->id,
        ]);

        AccountUser::create([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'role' => AccountRole::Owner,
            'can_edit' => true,
            'can_see_prices' => true,
            'can_see_invoices' => true,
            'can_see_contacts' => true,
            'accepted_at' => now(),
        ]);
    }

    /**
     * The wedding-makeup rate card from section 5.12 of the schema document,
     * priced for Bristol.
     */
    private function seedRateCard(): void
    {
        $rows = [
            ['Bride', 'The person getting married', ServiceKind::Service, ServiceAppliesTo::Main, 25000],
            ['Bridesmaid', null, ServiceKind::Service, ServiceAppliesTo::Main, 6500],
            ['Mother of the bride', null, ServiceKind::Service, ServiceAppliesTo::Main, 6500],
            ['Senior', 'Grandparents and older guests', ServiceKind::Service, ServiceAppliesTo::Main, 5500],
            ['Child', 'Under 16', ServiceKind::Service, ServiceAppliesTo::Main, 3000],
            ['Gentleman', 'Grooming and light coverage', ServiceKind::Service, ServiceAppliesTo::Main, 3500],
            ['Bridal trial', 'Full trial at the studio', ServiceKind::Service, ServiceAppliesTo::Trial, 8500],
            ['Additional trial', 'Any further trial', ServiceKind::Service, ServiceAppliesTo::Trial, 6000],
            ['Early start supplement', 'Start before 6.30am', ServiceKind::Service, ServiceAppliesTo::Main, 5000],
            ['Travel', 'Per mile beyond ten miles', ServiceKind::Travel, ServiceAppliesTo::Both, 45],
            ['Accommodation', 'At cost, agreed beforehand', ServiceKind::Expense, ServiceAppliesTo::Both, 0],
            ['Parking', 'At cost', ServiceKind::Expense, ServiceAppliesTo::Both, 0],
            ['Congestion or clean air charge', 'At cost', ServiceKind::Expense, ServiceAppliesTo::Both, 0],
            ['Other expense', null, ServiceKind::Expense, ServiceAppliesTo::Both, 0],
        ];

        $this->services = collect($rows)->mapWithKeys(function (array $row, int $index) {
            [$name, $description, $kind, $appliesTo, $price] = $row;

            $service = Service::create([
                'name' => $name,
                'description' => $description,
                'kind' => $kind,
                'applies_to' => $appliesTo,
                'price_minor' => $price,
                'sort_order' => $index,
                'active' => true,
            ]);

            return [$name => $service];
        });
    }

    private function seedContacts(): void
    {
        $rows = [
            ['sophie', 'Sophie', 'Bennett', 'sophie.bennett@example.com', '07700 900101', '14 Thurlow Rise', 'Bristol', 'BS6 6XJ'],
            ['hannah', 'Hannah', 'Whitfield', 'hannah.whitfield@example.com', '07700 900102', '31 Marlcombe Rise', 'Bath', 'BA2 6XE'],
            ['amelia', 'Amelia', 'Hart', 'amelia.hart@example.com', '07700 900103', '7 Peverell Walk', 'Cheltenham', 'GL50 2XB'],
            ['chloe', 'Chloe', 'Dawson', 'chloe.dawson@example.com', '07700 900104', '26 Halberton Rise', 'Exeter', 'EX4 6XN'],
            ['megan', 'Megan', 'Fletcher', 'megan.fletcher@example.com', '07700 900105', '12 Ashcott Row', 'Taunton', 'TA1 1XP'],
            ['lucy', 'Lucy', 'Pritchard', 'lucy.pritchard@example.com', '07700 900106', '48 Kempsley Way', 'Gloucester', 'GL1 3XR'],
            ['jessica', 'Jessica', 'Rowe', 'jessica.rowe@example.com', '07700 900107', '9 Sandbay Rise', 'Weston-super-Mare', 'BS23 1XQ'],
            ['emily', 'Emily', 'Carver', 'emily.carver@example.com', '07700 900108', '55 Draycott Mead', 'Swindon', 'SN1 4XT'],
            // The clients on the two days that collide. One each, because two
            // weddings on a Saturday are two different families.
            ['priya', 'Priya', 'Raman', 'priya.raman@example.com', '07700 900109', '3 Redwill Vale', 'Bristol', 'BS8 2XG'],
            ['rosie', 'Rosie', 'Kerr', 'rosie.kerr@example.com', '07700 900110', '18 Fennimore Place', 'Bath', 'BA1 2XL'],
            ['nadia', 'Nadia', 'Iqbal', 'nadia.iqbal@example.com', '07700 900111', '61 Aldergrove Walk', 'Cheltenham', 'GL52 2XW'],
            ['charlotte', 'Charlotte', 'Dean', 'charlotte.dean@example.com', '07700 900112', '24 Millbourne Row', 'Frome', 'BA11 1XD'],
            ['aoife', 'Aoife', 'Sheridan', 'aoife.sheridan@example.com', '07700 900113', '39 Havenscroft Road', 'Bristol', 'BS7 8XM'],
            ['martha', 'Martha', 'Oyelaran', 'martha.oyelaran@example.com', '07700 900114', '6 Berrymead Close', 'Gloucester', 'GL2 4XF'],
            // The three the enquiries screen exists for: a conversation that
            // has gone quiet, an enquiry with no date at all, and one the
            // artist turned down rather than lost.
            ['freya', 'Freya', 'Loxley', 'freya.loxley@example.com', '07700 900115', '21 Cranmoor Rise', 'Wells', 'BA5 1XN'],
            ['ines', 'Ines', 'Marchetti', 'ines.marchetti@example.com', '07700 900116', '8 Thornaby Green', 'Bath', 'BA2 3XV'],
            ['ottilie', 'Ottilie', 'Vance', 'ottilie.vance@example.com', '07700 900117', '44 Weatherby Row', 'Stroud', 'GL5 2XK'],
        ];

        foreach ($rows as [$key, $first, $last, $email, $phone, $address, $city, $postcode]) {
            $this->contacts[$key] = Contact::create([
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => $phone,
                'address_line_1' => $address,
                'city' => $city,
                'postcode' => $postcode,
                'country' => 'GB',
            ]);
        }
    }

    private function seedBookings(): void
    {
        $today = today();

        // 1. A brand new enquiry from the website.
        $this->bookings[1] = $this->booking('sophie', [
            'stage' => BookingStage::New,
            'source' => BookingSource::WebForm,
            'enquiry_message' => 'Hi Ellie, we are getting married at Harbourmead Hall next June and I would love to talk about makeup for me, three bridesmaids and my mum. Ready by 12.30 if possible.',
            'last_touched_at' => now()->subDays(1),
        ], [
            'date' => $today->addMonths(9)->addDays(10),
            'venue' => ['Harbourmead Hall', 'Sedgemoor Lane', 'Clevedon', 'BS21 7XN'],
            'start' => '07:00:00', 'ready' => '12:30:00',
        ], null, [
            ['Sophie', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Mum', 'Mother of the bride'],
        ], [], [
            'Came in through the website form. Reply within the day.',
        ]);

        // 2. A possible: in conversation, date pencilled in.
        $this->bookings[2] = $this->booking('hannah', [
            'stage' => BookingStage::Possible,
            'source' => BookingSource::Manual,
            'last_touched_at' => now()->subDays(4),
        ], [
            'date' => $today->addMonths(8)->addDays(6),
            'venue' => ['Wrenfield Mill', 'Barleyfield Lane', 'Bath', 'BA2 9XT'],
            'start' => '07:30:00', 'ready' => '12:00:00',
        ], null, [
            ['Hannah', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'],
        ], [
            ['Bride', 1], ['Bridesmaid', 2], ['Bridal trial', 1],
        ], [
            'Met at the Bath wedding fair. Very keen, waiting on her venue to confirm the date.',
            'Sister may also want makeup, she will let me know.',
        ]);

        // 3. Quoted, captured at a completed booking (set once booking 11 exists).
        $this->bookings[3] = $this->booking('amelia', [
            'stage' => BookingStage::Quoted,
            'source' => BookingSource::CapturedAtEvent,
            'last_touched_at' => now()->subDays(6),
        ], [
            'date' => $today->addMonths(7)->addDays(22),
            'venue' => ['Alderway Court', 'Thornhaugh Lane', 'Gloucester', 'GL2 3XP'],
            'start' => '06:30:00', 'ready' => '11:30:00',
        ], null, [
            ['Amelia', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Mum', 'Mother of the bride'],
        ], [
            ['Bride', 1], ['Bridesmaid', 4], ['Mother of the bride', 1], ['Bridal trial', 1], ['Early start supplement', 1],
        ], [
            'Was a bridesmaid at Lucy\'s wedding in July and asked for a card on the day.',
            'First quote was too high for six faces, sent a revised one without the early start.',
        ]);

        // 4. Provisional, hold still open, reminders snoozed.
        $this->bookings[4] = $this->booking('chloe', [
            'stage' => BookingStage::Provisional,
            'source' => BookingSource::WebForm,
            'converted_at' => now()->subDays(4),
            'last_touched_at' => now()->subDays(2),
        ], [
            'date' => $today->addMonths(6)->addDays(18),
            'venue' => ['Fallowmere House', 'Combewell Lane', 'Honiton', 'EX14 3XR'],
            'start' => '08:00:00', 'ready' => '13:00:00',
        ], $today->addMonths(5)->addDays(25), [
            ['Chloe', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'],
        ], [
            ['Bride', 1], ['Bridesmaid', 2], ['Bridal trial', 1], ['Travel', 120],
        ], [
            'Asked for a fortnight to sort the deposit, holding the date until then.',
        ]);

        // 5. Provisional with a hold that has lapsed.
        $this->bookings[5] = $this->booking('megan', [
            'stage' => BookingStage::Provisional,
            'source' => BookingSource::ForwardedEmail,
            'converted_at' => now()->subDays(20),
            'last_touched_at' => now()->subDays(19),
        ], [
            'date' => $today->addMonths(5)->addDays(11),
            'venue' => ['Netherstone Lodge', 'Fernbrook Lane', 'Blagdon', 'BS40 7XQ'],
            'start' => '07:30:00', 'ready' => '12:30:00',
        ], $today->addMonths(4)->addDays(20), [
            ['Megan', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Mum', 'Mother of the bride'], ['Nan', 'Senior'],
        ], [
            ['Bride', 1], ['Bridesmaid', 1], ['Mother of the bride', 1], ['Senior', 1], ['Bridal trial', 1],
        ], [
            'No deposit yet and the hold has run out. Chase once more, then release the date.',
        ]);

        // 6. Confirmed, deposit paid, trial booked.
        $this->bookings[6] = $this->booking('lucy', [
            'stage' => BookingStage::Confirmed,
            'source' => BookingSource::Manual,
            'converted_at' => now()->subWeeks(6),
            'confirmed_at' => now()->subWeeks(5),
            'last_touched_at' => now()->subDays(3),
        ], [
            'date' => $today->addMonth()->addDays(15),
            'venue' => ['Marlbury Court', 'Whitleigh Lane', 'Wotton-under-Edge', 'GL12 8XJ'],
            'start' => '07:00:00', 'ready' => '12:00:00',
        ], $today->addDays(17), [
            ['Lucy', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Mum', 'Mother of the bride'],
        ], [
            ['Bride', 1], ['Bridesmaid', 3], ['Mother of the bride', 1], ['Bridal trial', 1], ['Travel', 22],
        ], [
            'Trial booked for the studio. Wants a soft glam look, bringing photographs.',
            'Photographer arrives at 11, wants the bride ready for getting-ready shots.',
        ], [
            [BookingContactRole::Partner, 'Tom Pritchard', '07700 900201', null],
            [BookingContactRole::Planner, 'Kate Morgan, Tortworth events team', '01454 000000', 'Suppliers use the rear entrance.'],
        ]);

        // 7. Confirmed on a fixed price.
        $this->bookings[7] = $this->booking('jessica', [
            'stage' => BookingStage::Confirmed,
            'source' => BookingSource::Manual,
            'converted_at' => now()->subWeeks(8),
            'confirmed_at' => now()->subWeeks(7),
            'pricing_mode' => PricingMode::Fixed,
            'fixed_price_minor' => 95000,
            'fixed_price_description' => 'Full bridal party package, up to six faces including trial',
            'last_touched_at' => now()->subDays(8),
        ], [
            'date' => $today->addMonths(2)->addDays(26),
            'venue' => ['Pearmain House', 'Marlpit Lane', 'Frome', 'BA11 2XW'],
            'start' => '07:30:00', 'ready' => '13:00:00',
        ], $today->addMonths(2)->addDays(5), [
            ['Jessica', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Mum', 'Mother of the bride'], ['Mother-in-law', 'Mother of the bride'],
        ], [
            ['Bride', 1], ['Bridesmaid', 3], ['Mother of the bride', 2], ['Bridal trial', 1],
        ], [
            'Agreed a package price rather than itemising, she preferred one figure.',
            'Agreement redrafted to add the second trial, waiting for her to sign the new version.',
        ]);

        // 8. Confirmed with a discount, already paid in full.
        $this->bookings[8] = $this->booking('emily', [
            'stage' => BookingStage::Confirmed,
            'source' => BookingSource::WebForm,
            'converted_at' => now()->subWeeks(10),
            'confirmed_at' => now()->subWeeks(9),
            'discount_type' => DiscountType::Percent,
            'discount_value' => 10,
            'discount_reason' => 'Winter wedding',
            'last_touched_at' => now()->subDays(12),
        ], [
            'date' => $today->addMonths(4)->addDays(14),
            'venue' => ['Saltway Barn', 'Cranmore Row', 'Tetbury', 'GL8 8XD'],
            'start' => '08:00:00', 'ready' => '13:30:00',
        ], $today->addMonths(3)->addDays(20), [
            ['Emily', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'],
        ], [
            ['Bride', 1], ['Bridesmaid', 2], ['Bridal trial', 1], ['Travel', 30],
        ], [
            'Paid the whole invoice up front, nothing more to chase.',
        ]);

        // 9. Confirmed, deposit paid twice by mistake and one refunded.
        $this->bookings[9] = $this->booking('jessica', [
            'stage' => BookingStage::Confirmed,
            'source' => BookingSource::Manual,
            'converted_at' => now()->subWeeks(3),
            'confirmed_at' => now()->subWeeks(2),
            'last_touched_at' => now()->subDays(5),
        ], [
            'date' => $today->addMonths(10)->addDays(8),
            'venue' => ['Quillet Court', 'Greenway Rise', 'Bristol', 'BS41 9XG'],
            'start' => '07:30:00', 'ready' => '12:30:00',
        ], $today->addMonths(9)->addDays(15), [
            ['Sarah', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Mum', 'Mother of the bride'],
        ], [
            ['Bride', 1], ['Bridesmaid', 2], ['Mother of the bride', 1], ['Bridal trial', 1],
        ], [
            'Jessica booking on behalf of her sister Sarah.',
            'Deposit came through twice on the same day, refunded the second one.',
        ]);

        // 10. Confirmed, deposit paid.
        $this->bookings[10] = $this->booking('hannah', [
            'stage' => BookingStage::Confirmed,
            'source' => BookingSource::Manual,
            'converted_at' => now()->subWeeks(4),
            'confirmed_at' => now()->subWeeks(3),
            'last_touched_at' => now()->subDays(9),
        ], [
            'date' => $today->addMonths(11)->addDays(19),
            'venue' => ['The Verity Rooms', 'Pindar Street', 'Bath', 'BA1 2XF'],
            'start' => '08:00:00', 'ready' => '13:00:00',
        ], $today->addMonths(10)->addDays(28), [
            ['Hannah', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'],
        ], [
            ['Bride', 1], ['Bridesmaid', 4], ['Bridal trial', 1], ['Parking', 1],
        ], [
            'Booking for Hannah\'s cousin. City centre venue, allow for parking.',
        ]);

        // 11. Completed with a balance still owing. Amelia was a bridesmaid here.
        $this->bookings[11] = $this->booking('lucy', [
            'stage' => BookingStage::Completed,
            'source' => BookingSource::Manual,
            'converted_at' => now()->subMonths(7),
            'confirmed_at' => now()->subMonths(6),
            'last_touched_at' => now()->subDays(15),
        ], [
            'date' => $today->subMonth()->subDays(22),
            'venue' => ['Brackendown Lodge', 'Ellerslie Drive', 'Bristol', 'BS10 7XB'],
            'start' => '07:00:00', 'ready' => '12:00:00',
        ], $today->subMonths(2)->subDays(20), [
            ['Lucy', 'Bride'], ['Amelia', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Mum', 'Mother of the bride'],
        ], [
            ['Bride', 1], ['Bridesmaid', 2], ['Mother of the bride', 1], ['Bridal trial', 1],
        ], [
            'Lovely day. Balance still outstanding, sent a gentle reminder.',
            'Amelia (bridesmaid) took a card and has since enquired for her own wedding.',
        ]);

        // 12. Completed, balance overdue.
        $this->bookings[12] = $this->booking('sophie', [
            'stage' => BookingStage::Completed,
            'source' => BookingSource::VoiceNote,
            'converted_at' => now()->subMonths(6),
            'confirmed_at' => now()->subMonths(5),
            'last_touched_at' => now()->subDays(10),
        ], [
            'date' => $today->subDays(18),
            'venue' => ['Sheldrake House', 'Ferndown Lane', 'Bristol', 'BS11 0XL'],
            'start' => '07:30:00', 'ready' => '12:30:00',
        ], $today->subMonth()->subDays(10), [
            ['Grace', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Nan', 'Senior'], ['Flower girl', 'Child'],
        ], [
            ['Bride', 1], ['Bridesmaid', 3], ['Senior', 1], ['Child', 1], ['Bridal trial', 1], ['Early start supplement', 1],
        ], [
            'Sophie booked this for her sister Grace.',
            'Balance was due before the day and is still outstanding. Chase this week.',
        ]);

        // 13. Closed and fully paid.
        $this->bookings[13] = $this->booking('emily', [
            'stage' => BookingStage::Closed,
            'source' => BookingSource::Manual,
            'converted_at' => now()->subMonths(9),
            'confirmed_at' => now()->subMonths(8),
            'last_touched_at' => now()->subMonths(2),
        ], [
            'date' => $today->subMonths(2)->subDays(27),
            'venue' => ['The Pallantine Rooms', 'Alverton Square', 'Cheltenham', 'GL50 1XN'],
            'start' => '08:00:00', 'ready' => '13:00:00',
        ], $today->subMonths(3)->subDays(20), [
            ['Emily', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Mum', 'Mother of the bride'],
        ], [
            ['Bride', 1], ['Bridesmaid', 2], ['Mother of the bride', 1], ['Bridal trial', 1], ['Travel', 44],
        ], [
            'All paid, thank you sent, photographs received. Closed.',
        ]);

        // 14. Lost: the client went elsewhere over the price. One of the five
        // endings on the client's side of App\Enums\LostReason.
        $this->bookings[14] = $this->booking('megan', [
            'stage' => BookingStage::Lost,
            'source' => BookingSource::WebForm,
            'lost_reason' => LostReason::TooExpensive,
            'lost_at' => now()->subWeeks(3),
            'last_touched_at' => now()->subWeeks(3),
        ], [
            'date' => $today->addYear()->addDays(2),
            'venue' => ['The Conduit Room', 'Cobbett Street', 'Bath', 'BA1 1XM'],
            'start' => '08:00:00', 'ready' => '13:00:00',
        ], null, [
            ['Megan', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'],
        ], [
            ['Bride', 1], ['Bridesmaid', 2], ['Bridal trial', 1],
        ], [
            'Went with a friend of the family who is doing it for free. No hard feelings.',
        ]);

        // 15. In conversation, gone quiet. The stage between an enquiry
        // arriving and the soft hold at Possible (business logic 5.1), which
        // the account had no example of at all, and which is where the widened
        // artist_enquiry_cold branch now reaches.
        $this->bookings[15] = $this->booking('freya', [
            'stage' => BookingStage::InConversation,
            'source' => BookingSource::CapturedAtEvent,
            'last_touched_at' => now()->subDays(config('bookings.cold_enquiry_days') + 5),
        ], [
            'date' => $today->addMonths(10)->addDays(4),
            'venue' => ['Bramfield Tithe House', 'Kerrowmoor Lane', 'Wells', 'BA5 2XG'],
            'start' => '07:30:00', 'ready' => '12:30:00',
        ], null, [
            ['Freya', 'Bride'], ['Bridesmaid', 'Bridesmaid'],
        ], [], [
            'Met at a wedding in the spring. Two of them, no firm date for a trial yet.',
            'Said she would come back with times. Nothing since.',
        ]);

        // 16. An enquiry with no date at all, which the booking() helper cannot
        // make because it always writes a main event. "Somewhere next summer,
        // the venue is not settled" is normal and often winnable, and it is the
        // case an events-shaped payload cannot represent, because there is no
        // row for it.
        $this->bookings[16] = Booking::create([
            'contact_id' => $this->contacts['ines']->id,
            'currency' => 'GBP',
            'created_by_user_id' => $this->owner->id,
            'stage' => BookingStage::InConversation,
            'source' => BookingSource::VoiceNote,
            'enquiry_message' => 'Rang about next year. No date yet, they are between two venues and will not know until the autumn. Wants a rough idea of the cost before committing to either.',
            'last_touched_at' => now()->subDays(8),
        ]);

        Note::create([
            'booking_id' => $this->bookings[16]->id,
            'user_id' => $this->owner->id,
            'body' => 'No date to hold and nothing to quote yet. Worth a nudge once the venue is settled.',
            'created_at' => $this->bookings[16]->last_touched_at,
        ]);

        // The quoted enquiry was captured on the day of the completed booking.
        $this->bookings[3]->update(['source_booking_id' => $this->bookings[11]->id]);

        $this->seedCollisionDays();
    }

    /**
     * The two days the calendar exists to show, seeded as properties rather
     * than as dates because the seeder runs whenever it runs.
     *
     * Business logic 19.1 names the first as the case the whole screen is
     * built around: one confirmed booking and three live enquiries on the same
     * Saturday, where the day mark has to say both things at once. Without it
     * the demo account cannot demonstrate the feature it is a demo of, which
     * is how it stood until now.
     *
     * 5.2 names the second: two weddings in one day is normal in this trade,
     * and the clash warning exists because of it rather than in spite of it.
     */
    private function seedCollisionDays(): void
    {
        $this->seedBusySaturday();
        $this->seedTwoWeddingsInADay();
    }

    /**
     * One confirmed booking and three enquiries on one Saturday.
     *
     * Far enough out to sit in the list's "next three months" group on first
     * load, and in the future because three live enquiries on a date that has
     * already passed is nonsense rather than demo data.
     */
    private function seedBusySaturday(): void
    {
        $date = $this->saturdayAtLeast(60);

        $this->bookings[17] = $this->booking('priya', [
            'stage' => BookingStage::Confirmed,
            'source' => BookingSource::WebForm,
            'converted_at' => now()->subMonths(4),
            'confirmed_at' => now()->subMonths(3),
            'last_touched_at' => now()->subDays(12),
        ], [
            'date' => $date,
            'venue' => ['Cathermere Barn', 'Lynmore Lane', 'Berkeley', 'GL13 9XE'],
            'start' => '06:30:00', 'ready' => '12:00:00',
            // Seven weeks and not six: six lands this trial on the last
            // Saturday of the month, which is the two-wedding day below, and a
            // ten o'clock trial between a half five start and a one o'clock
            // ceremony is not a busy day, it is an impossible one.
        ], $date->subWeeks(7), [
            ['Priya', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Mum', 'Mother of the bride'],
        ], [
            ['Bride', 1], ['Bridesmaid', 2], ['Mother of the bride', 1], ['Bridal trial', 1],
        ], [
            'Booked at the wedding fair in March. Very organised, everything agreed already.',
        ]);

        // Three enquiries on the same date, each from a different family, so
        // the count badge reads three rather than one booking listed oddly.
        $this->bookings[18] = $this->booking('rosie', [
            'stage' => BookingStage::Possible,
            'source' => BookingSource::WebForm,
            'enquiry_message' => 'Hello, we are at Larkspur Court that day and there are five of us. Is there any chance you are still free?',
            'last_touched_at' => now()->subDays(3),
        ], [
            'date' => $date,
            'venue' => ['Larkspur Court', 'Danesbrook Road', 'Stroud', 'GL6 0XQ'],
            'start' => '07:00:00', 'ready' => '13:00:00',
        ], null, [
            ['Rosie', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'],
        ], [], [
            'Same Saturday as Priya Raman. Worth a call before it goes any further.',
        ]);

        $this->bookings[19] = $this->booking('nadia', [
            'stage' => BookingStage::Quoted,
            'source' => BookingSource::ForwardedEmail,
            'enquiry_message' => 'Forwarded from the venue. Bride plus four, ready by one.',
            'last_touched_at' => now()->subDays(9),
        ], [
            'date' => $date,
            'venue' => ['The Rookery Rooms', 'Kingsmill Street', 'Cheltenham', 'GL52 2XN'],
            'start' => '07:30:00', 'ready' => '13:00:00',
        ], null, [
            ['Nadia', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'], ['Mum', 'Mother of the bride'],
        ], [
            ['Bride', 1], ['Bridesmaid', 3], ['Mother of the bride', 1],
        ], [
            'Quoted on the same Saturday as two others. First to sign takes it.',
        ]);

        $this->bookings[20] = $this->booking('charlotte', [
            'stage' => BookingStage::Possible,
            'source' => BookingSource::Manual,
            'enquiry_message' => 'Rang about the same weekend. Venue not settled yet, somewhere near Bath.',
            'last_touched_at' => now()->subDays(26),
        ], [
            'date' => $date,
            'venue' => ['Stonewell Grange', 'Halstock Hill', 'Bath', 'BA1 7XR'],
            'start' => '08:00:00', 'ready' => '13:30:00',
        ], null, [
            ['Charlotte', 'Bride'], ['Bridesmaid', 'Bridesmaid'],
        ], [], [
            'Gone quiet since the first call. Third enquiry on that Saturday.',
        ]);

        // The fourth name on the same Saturday, and the one the artist ended
        // rather than lost. It is on this date on purpose: turning an enquiry
        // down is the moment the other names on the date matter most, and
        // already_booked is the artist's side of App\Enums\LostReason
        // (decision 2026-09-06.1512). It carries no calendar mark and no clash
        // of its own, because a lost enquiry has released the date.
        $this->bookings[21] = $this->booking('ottilie', [
            'stage' => BookingStage::Lost,
            'source' => BookingSource::WebForm,
            'enquiry_message' => 'We are at Ashen Hollow on that Saturday, six of us and a very early start. Could you manage four in the morning?',
            'lost_reason' => LostReason::AlreadyBooked,
            'lost_at' => now()->subWeeks(4),
            'last_touched_at' => now()->subWeeks(4),
        ], [
            'date' => $date,
            'venue' => ['Ashen Hollow', 'Wraycombe Lane', 'Chepstow', 'NP16 5XB'],
            'start' => '04:00:00', 'ready' => '11:00:00',
        ], null, [
            ['Ottilie', 'Bride'], ['Bridesmaid', 'Bridesmaid'],
        ], [], [
            'Had to say no. Priya Raman is the same day and was confirmed months ago.',
        ]);
    }

    /**
     * Two weddings worked in one day, in the current month so a mark is on
     * screen the moment the app opens.
     *
     * The last Saturday of the month is usually behind us by the time anybody
     * runs this, so the stage follows the date rather than the date following
     * the stage: a past date is seeded as completed, which decision 187 marks
     * exactly as confirmed, so the calendar looks the same and the data stays
     * honest. Seeding a past Saturday as confirmed to satisfy a test would be
     * writing a booking that has not happened yet into last week.
     */
    private function seedTwoWeddingsInADay(): void
    {
        $date = $this->lastSaturdayOfThisMonth();
        $stage = $date->lessThan(today()) ? BookingStage::Completed : BookingStage::Confirmed;

        $this->bookings[22] = $this->booking('aoife', [
            'stage' => $stage,
            'source' => BookingSource::WebForm,
            'converted_at' => now()->subMonths(8),
            'confirmed_at' => now()->subMonths(7),
            'last_touched_at' => now()->subDays(20),
        ], [
            'date' => $date,
            'venue' => ['Ferrenden Hall', 'Ravensmead Road', 'Bath', 'BA1 9XD'],
            // The early start, which is what makes two in a day possible at
            // all: the first is finished before the second is awake.
            'start' => '05:30:00', 'ready' => '10:30:00',
        ], $date->subWeeks(8), [
            ['Aoife', 'Bride'], ['Bridesmaid', 'Bridesmaid'], ['Bridesmaid', 'Bridesmaid'],
        ], [
            ['Bride', 1], ['Bridesmaid', 2], ['Early start supplement', 1], ['Bridal trial', 1],
        ], [
            'Five thirty start so the afternoon is free for the second wedding.',
        ]);

        $this->bookings[23] = $this->booking('martha', [
            'stage' => $stage,
            'source' => BookingSource::Manual,
            'converted_at' => now()->subMonths(6),
            'confirmed_at' => now()->subMonths(5),
            'last_touched_at' => now()->subDays(20),
        ], [
            'date' => $date,
            'venue' => ['The Mulberry Room', 'Ludworth Street', 'Gloucester', 'GL1 2XT'],
            'start' => '13:00:00', 'ready' => '16:30:00',
        ], null, [
            ['Martha', 'Bride'], ['Bridesmaid', 'Bridesmaid'],
        ], [
            ['Bride', 1], ['Bridesmaid', 1],
        ], [
            'Afternoon ceremony, so both fit in the day. Booked knowing about the morning one.',
        ]);
    }

    /**
     * The first Saturday at least this many days from today.
     */
    private function saturdayAtLeast(int $days): CarbonImmutable
    {
        $date = today()->addDays($days);

        return $date->isSaturday() ? $date : $date->next(CarbonInterface::SATURDAY);
    }

    /**
     * The last Saturday of the month we are in, which always exists and is
     * always inside the month the calendar opens on.
     */
    private function lastSaturdayOfThisMonth(): CarbonImmutable
    {
        $end = today()->endOfMonth()->startOfDay();

        return $end->isSaturday() ? $end : $end->previous(CarbonInterface::SATURDAY);
    }

    /**
     * Create a booking with its main event, optional trial event at the
     * studio, party, lines, notes and extra contacts.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array{date: CarbonImmutable, venue: array{0: string, 1: string, 2: string, 3: string}, start: string, ready: string}  $main
     * @param  array<int, array{0: string, 1: string}>  $party  name and rate card row
     * @param  array<int, array{0: string, 1: int}>  $lines  rate card row and quantity
     * @param  array<int, string>  $notes
     * @param  array<int, array{0: BookingContactRole, 1: string, 2: string|null, 3: string|null}>  $extraContacts
     */
    /**
     * Compute hold_expires_at the way a real stage change would, rather than
     * writing the column out by hand.
     *
     * **A seeder that hand-sets a column the application computes is the same
     * failure as decisions 220 and 227**, arriving a third time: 220 found the
     * seeder had drifted from what registration produces, 227 found it could
     * not demonstrate the case the bookings screen exists for. It matters most
     * here, because this whole change exists to stop artist_not_held being
     * fiction, and a demo account still faking it would leave the thing
     * unfinished.
     *
     * The day passed to App\Services\SoftHold is the day this record reached
     * its current stage, which for a provisional booking is converted_at and is
     * already in the fixture. That is what lets a lapsed hold be seeded
     * honestly: computed from today it could only ever be live, and a seeder
     * wanting the lapsed case would be straight back to setting the column by
     * hand.
     *
     * The fixtures corroborate the fourteen-day default rather than being bent
     * to fit it. The two rows that used to carry a literal were converted_at
     * plus ten and minus five against conversions four and twenty days old,
     * which is a fourteen-day hold written out longhand.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withHold(array $attributes): array
    {
        $stage = $attributes['stage'] ?? BookingStage::New;
        $hold = app(SoftHold::class);

        if ($hold->classOf($stage) === HoldClass::None) {
            return $attributes;
        }

        return $attributes + [
            'hold_expires_at' => $hold->forTransition(
                from: null,
                to: $stage,
                on: $attributes['converted_at'] ?? $attributes['last_touched_at'] ?? null,
            ),
        ];
    }

    private function booking(
        string $contactKey,
        array $attributes,
        array $main,
        ?CarbonImmutable $trialDate,
        array $party,
        array $lines,
        array $notes,
        array $extraContacts = [],
    ): Booking {
        $attributes = $this->withHold($attributes);

        $booking = Booking::create(array_merge([
            'contact_id' => $this->contacts[$contactKey]->id,
            'currency' => 'GBP',
            'created_by_user_id' => $this->owner->id,
        ], $attributes));

        [$venueName, $street, $town, $postcode] = $main['venue'];

        $mainEvent = Event::create([
            'booking_id' => $booking->id,
            'type' => EventType::Main,
            'event_date' => $main['date'],
            'start_time' => $main['start'],
            'ready_by_time' => $main['ready'],
            'timezone' => 'Europe/London',
            'location_type' => LocationType::Venue,
            'address_line_1' => $street,
            'city' => $town,
            'postcode' => $postcode,
            'country' => 'GB',
            'venue_name' => $venueName,
            'sort_order' => 1,
        ]);

        if ($trialDate !== null) {
            Event::create([
                'booking_id' => $booking->id,
                'type' => EventType::Trial,
                'event_date' => $trialDate,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'timezone' => 'Europe/London',
                'location_type' => LocationType::Base,
                'sort_order' => 0,
            ]);
        }

        foreach ($party as $index => [$name, $serviceName]) {
            $service = $this->services[$serviceName];

            PartyMember::create([
                'booking_id' => $booking->id,
                'event_id' => $mainEvent->id,
                'name' => $name,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'sort_order' => $index,
            ]);
        }

        foreach ($lines as $index => [$serviceName, $quantity]) {
            $service = $this->services[$serviceName];

            BookingLine::create([
                'booking_id' => $booking->id,
                'service_id' => $service->id,
                'kind' => LineKind::from($service->kind->value),
                'description' => $service->name,
                'quantity' => $quantity,
                'unit_price_minor' => $service->price_minor->minor,
                'sort_order' => $index,
            ]);
        }

        foreach ($notes as $index => $body) {
            Note::create([
                'booking_id' => $booking->id,
                'user_id' => $this->owner->id,
                'body' => $body,
                'created_at' => $booking->last_touched_at->subDays(count($notes) - $index),
            ]);
        }

        foreach ($extraContacts as [$role, $name, $phone, $note]) {
            BookingContact::create([
                'booking_id' => $booking->id,
                'role' => $role,
                'name' => $name,
                'phone' => $phone,
                'note' => $note,
            ]);
        }

        // Reload so the column defaults the database filled in (stage,
        // pricing_mode and so on) are present on the model.
        return $booking->refresh();
    }

    private function seedQuotes(): void
    {
        // Two on the quoted enquiry: the first came back too high, the second
        // is the one the client is considering.
        $enquiry = $this->bookings[3];
        $this->quote($enquiry, 1, QuoteStatus::Declined, now()->subDays(12), [
            'subtotal' => 62000, 'total' => 62000,
        ]);
        $this->quote($enquiry, 2, QuoteStatus::Sent, now()->subDays(6));

        // One accepted quote on a confirmed booking.
        $this->quote($this->bookings[6], 1, QuoteStatus::Accepted, now()->subWeeks(6));
    }

    /**
     * @param  array<string, int>  $override  subtotal and total in minor units when the snapshot differs from the live lines
     */
    private function quote(Booking $booking, int $number, QuoteStatus $status, CarbonImmutable $sentAt, array $override = []): void
    {
        $subtotal = $override['subtotal'] ?? $this->pricing->subtotal($booking)->minor;
        $discount = $this->pricing->discount($booking)->minor;
        $total = $override['total'] ?? $this->pricing->total($booking)->minor;
        $deposit = $this->pricing->deposit($booking)->minor;

        Quote::create([
            'booking_id' => $booking->id,
            'number' => $number,
            'currency' => 'GBP',
            'pricing_mode' => $booking->pricing_mode,
            'lines' => $this->pricing->lineSnapshot($booking),
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'total_minor' => $total,
            'deposit_minor' => $deposit,
            'rendered_text' => $this->render($this->systemTemplateBody('quote'), $booking, $total),
            'status' => $status,
            'sent_at' => $sentAt,
            'sent_via' => QuoteSentVia::Copy,
            'responded_at' => $status === QuoteStatus::Sent ? null : $sentAt->addDays(2),
            'valid_until' => $sentAt->addDays(14)->toDateString(),
            'created_by_user_id' => $this->owner->id,
        ]);
    }

    /**
     * Invoices are issued through InvoiceNumbering in booking order, so the
     * numbers run INV-0001 upwards with no gaps.
     */
    private function seedInvoices(): void
    {
        $plan = [
            4 => 'unpaid_snoozed',
            5 => 'unpaid',
            6 => 'deposit',
            7 => 'deposit',
            8 => 'paid',
            9 => 'deposit_twice_refunded',
            10 => 'deposit',
            11 => 'deposit',
            12 => 'deposit',
            13 => 'paid',
        ];

        foreach ($plan as $index => $state) {
            $booking = $this->bookings[$index];

            $invoice = Invoice::create([
                'booking_id' => $booking->id,
                'status' => InvoiceStatus::Draft,
                'currency' => 'GBP',
                'lines' => [],
                'subtotal_minor' => 0,
                'total_minor' => 0,
                'created_by_user_id' => $this->owner->id,
            ]);

            $this->numbering->issue($invoice);

            // Backdate the paperwork to when the booking was confirmed, so the
            // due dates read sensibly against the event.
            $issuedOn = ($booking->confirmed_at ?? $booking->converted_at ?? now())->toImmutable()->startOfDay();
            $invoice->forceFill([
                'issued_on' => $issuedOn,
                'deposit_due_on' => $issuedOn->addDays(7),
                'reminders_snoozed_until' => $state === 'unpaid_snoozed' ? today()->addDays(14) : null,
            ])->save();

            $deposit = $invoice->deposit_minor->minor;
            $total = $invoice->total_minor->minor;

            match ($state) {
                'unpaid', 'unpaid_snoozed' => null,
                'deposit' => $this->payment($invoice, $deposit, $issuedOn->addDays(3)),
                'paid' => [
                    $this->payment($invoice, $deposit, $issuedOn->addDays(2)),
                    $this->payment($invoice, $total - $deposit, $issuedOn->addDays(30), PaymentMethod::BankTransfer, 'Balance'),
                ],
                'deposit_twice_refunded' => [
                    $this->payment($invoice, $deposit, $issuedOn->addDays(4)),
                    $this->payment($invoice, $deposit, $issuedOn->addDays(4), PaymentMethod::BankTransfer, 'Duplicate'),
                    $this->payment($invoice, -$deposit, $issuedOn->addDays(6), PaymentMethod::BankTransfer, 'Refund', 'Deposit paid twice by mistake, second payment refunded.'),
                ],
            };
        }
    }

    private function payment(Invoice $invoice, int $amountMinor, CarbonImmutable $paidOn, PaymentMethod $method = PaymentMethod::BankTransfer, ?string $reference = null, ?string $note = null): void
    {
        Payment::create([
            'booking_id' => $invoice->booking_id,
            'invoice_id' => $invoice->id,
            'amount_minor' => $amountMinor,
            'paid_on' => $paidOn,
            'method' => $method,
            'reference' => $reference ?? strtoupper($invoice->booking->contact->last_name).' '.$invoice->number,
            'note' => $note,
            'recorded_by_user_id' => $this->owner->id,
        ]);
    }

    /**
     * Version 1 signed manually on every confirmed, completed and closed
     * booking, and a second draft version on the fixed-price booking whose
     * scope changed.
     */
    private function seedAgreements(): void
    {
        $template = ContractTemplate::query()
            ->whereNull('account_id')
            ->where('market', 'GB')
            ->where('vertical', SystemDefaultsSeeder::VERTICAL)
            ->orderByDesc('version')
            ->firstOrFail();

        foreach ([6, 7, 8, 9, 10, 11, 12, 13] as $index) {
            $booking = $this->bookings[$index];
            $signedAt = ($booking->confirmed_at ?? now())->toImmutable();

            $this->agreement($booking, $template, 1, AgreementStatus::Signed, $signedAt);
        }

        $this->agreement($this->bookings[7], $template, 2, AgreementStatus::Draft, null);
    }

    private function agreement(Booking $booking, ContractTemplate $template, int $version, AgreementStatus $status, ?CarbonImmutable $signedAt): void
    {
        $total = $this->pricing->total($booking)->minor;
        $body = $this->render($template->body, $booking, $total);

        Agreement::create([
            'booking_id' => $booking->id,
            'contract_template_id' => $template->id,
            'version' => $version,
            'status' => $status,
            'rendered_body' => $body,
            'rendered_sha256' => Agreement::hashBody($body),
            'total_minor' => $total,
            'deposit_minor' => $this->pricing->deposit($booking)->minor,
            'sent_at' => $signedAt?->subDays(2),
            'signed_at' => $signedAt,
            'signed_method' => $signedAt === null ? null : SignedMethod::Manual,
            'signed_name' => $signedAt === null ? null : $booking->contact->fullName(),
            'signed_note' => $signedAt === null ? null : 'Signed copy received by email.',
            'created_by_user_id' => $this->owner->id,
        ]);
    }

    private function systemTemplateBody(string $key): string
    {
        return MessageTemplate::withoutGlobalScope('account')
            ->whereNull('account_id')
            ->where('key', $key)
            ->where('locale', 'en-GB')
            ->value('body') ?? '';
    }

    /**
     * Replace the {{merge_fields}} in a template with this booking's values.
     * A stand-in for the rendering service that arrives with messaging; the
     * seeder only needs believable text.
     */
    private function render(string $template, Booking $booking, int $totalMinor): string
    {
        $main = $booking->mainEvent;
        $settings = $this->account->settings;
        $total = new Money($totalMinor, 'GBP');
        $deposit = $this->pricing->deposit($booking);
        $balance = $total->subtract($deposit);
        $balanceDueOn = $main?->event_date->subDays($settings->balance_due_days_before);

        $fields = [
            'contact_first_name' => $booking->contact->first_name,
            'contact_name' => $booking->contact->fullName(),
            'business_name' => $this->account->name,
            'main_event_date' => $main?->event_date->format('j F Y') ?? '',
            'main_event_day' => $main?->event_date->format('l') ?? '',
            'start_time' => $main?->start_time ? substr($main->start_time, 0, 5) : '',
            'address' => $main ? implode(', ', array_filter([$main->venue_name, $main->address_line_1, $main->city, $main->postcode])) : '',
            'total' => $total->format('en-GB'),
            'deposit' => $deposit->format('en-GB'),
            'balance' => $balance->format('en-GB'),
            'balance_due_on' => $balanceDueOn?->format('j F Y') ?? '',
            'payment_instructions' => $settings->payment_instructions ?? '',
            'sign_off' => 'Ellie x',
            'location_block' => '',
        ];

        foreach ($fields as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }
}

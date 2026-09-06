<?php

namespace App\Http\Controllers;

use App\Enums\BookingStage;
use App\Enums\FeatureKey;
use App\Enums\WaitingOn;
use App\Http\Resources\HomeResource;
use App\Models\Account;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Invoice;
use App\Services\AttentionRows;
use App\Services\BookingValue;
use App\Services\BusinessPeriods;
use App\Services\Features;
use App\Services\OutstandingBalances;
use App\Services\PaymentsReceived;
use App\Support\AttentionRow;
use App\Support\CurrentAccount;
use App\Support\HomeSummary;
use App\Support\MoneySummary;
use App\Support\OutstandingSplit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the home screen reads: business logic section 18's three blocks in one
 * payload.
 *
 * **One route rather than three.** See App\Http\Resources\HomeResource for why;
 * the short version is that the owed headline is the sum of the attention
 * block's client_balance rows, so splitting them means computing the same thing
 * twice or letting the client define a money figure.
 *
 * Scoped by the account global scope, which the `account` middleware binds
 * before this runs. Nothing here writes where('account_id', ...) by hand and
 * nothing reaches for DB::table(): every aggregate below reads like a
 * query-builder job, and written that way it totals every account's money while
 * looking perfectly correct in a development database with one account in it.
 *
 * **The cost is the thing to be careful about here.** The waiting-on axis is
 * asked of every live booking rather than of a filtered list, and a naive
 * implementation measured 201 queries for an account with forty of them. The
 * eager load below takes it to a constant; HomeQueryCountTest holds it there
 * with a literal.
 */
class HomeController extends Controller
{
    /**
     * Everything App\Services\WaitingOnResolver reads, plus what a row draws.
     *
     * The two that look redundant are not, and this is the third endpoint to
     * need them: booking_lines and payments have no currency column, so
     * MoneyCast resolves theirs through the booking, and without the extra hop
     * that is a query per line and per payment.
     *
     * @var array<int, string>
     */
    private const ATTENTION_RELATIONS = [
        'contact',
        'events',
        'lines',
        'lines.booking',
        'quotes',
        'agreements',
        'invoices.payments',
        'invoices.payments.booking',
        'account.settings',
    ];

    public function __construct(
        private readonly CurrentAccount $account,
        private readonly AttentionRows $attention,
        private readonly BusinessPeriods $periods,
        private readonly PaymentsReceived $received,
        private readonly BookingValue $value,
        private readonly OutstandingBalances $outstanding,
        private readonly Features $features,
    ) {}

    public function index(): HomeResource
    {
        $account = $this->account->require();
        $today = CarbonImmutable::today($account->timezone);

        $live = $this->liveBookings();
        $rows = $this->attention->for($live, $today);

        return new HomeResource(new HomeSummary(
            // Capped after the ordering, never before it, so what survives is
            // the most urgent rather than an arbitrary slice.
            attention: array_slice($rows, 0, (int) config('bookings.max_attention')),
            attentionTotal: count($rows),
            upcoming: $this->upcoming($today),
            money: $this->money($account->currency, $today, $rows, $live),
            features: $this->features($account),
            today: $today,
            timezone: $account->timezone,
        ));
    }

    /**
     * Every booking that could be waiting on something.
     *
     * Lost and cancelled are excluded here rather than being fetched and
     * discarded, which is not a second opinion about the resolver's guard: it
     * answers null for both, and this is the query saying it will not ask about
     * rows whose answer is already known. The saving is real on an account with
     * years of archive behind it.
     *
     * @return Collection<int, Booking>
     */
    private function liveBookings(): Collection
    {
        return Booking::query()
            ->whereNotIn('stage', [BookingStage::Lost->value, BookingStage::Cancelled->value])
            ->with(self::ATTENTION_RELATIONS)
            ->get();
    }

    /**
     * The next few events across confirmed and provisional bookings.
     *
     * Ordered by date, then start time with nulls last, then id, which is the
     * same total order GET /api/events uses: an event with no call time belongs
     * at the end of its day, and without the final id two events at the same
     * time could swap between requests.
     *
     * @return Collection<int, Event>
     */
    private function upcoming(CarbonImmutable $today): Collection
    {
        return Event::query()
            ->where('event_date', '>=', $today->toDateString())
            ->whereHas('booking', fn ($query) => $query->whereIn('stage', [
                BookingStage::Confirmed->value,
                BookingStage::Provisional->value,
            ]))
            ->with(['booking.contact', 'booking.partyMembers', 'booking.account.settings'])
            ->orderBy('event_date')
            ->orderByRaw('start_time asc nulls last')
            ->orderBy('id')
            ->limit((int) config('bookings.home_upcoming'))
            ->get();
    }

    /**
     * The money block.
     *
     * **Never removed by a feature toggle** (business logic 21.2, read
     * properly): what the toggles take away is the cash half, one figure at a
     * time. A booking carries a price whether or not anybody raised an invoice
     * for it, so booked ahead and provisional are knowable on every account.
     *
     * @param  array<int, AttentionRow>  $rows
     * @param  Collection<int, Booking>  $live
     */
    private function money(string $currency, CarbonImmutable $today, array $rows, Collection $live): MoneySummary
    {
        $account = $this->account->require();
        $invoicing = $this->features->enabled($account, FeatureKey::Invoicing);
        $tracking = $this->features->enabled($account, FeatureKey::PaymentTracking);

        $ranges = $this->periods->all($account);
        $earliest = $this->periods->earliest($account);

        $ahead = $this->value->ahead(BookingStage::Confirmed, $today, $currency);
        $provisional = $this->value->ahead(BookingStage::Provisional, $today, $currency);

        $owed = $invoicing ? $this->owed($rows) : null;

        return new MoneySummary(
            currency: $currency,
            basis: $tracking ? MoneySummary::BASIS_PAYMENTS : MoneySummary::BASIS_BOOKING_VALUE,
            excludesOtherCurrencies: $tracking
                ? $this->received->hasOtherCurrencies($earliest, $currency)
                : $this->value->hasOtherCurrencies($earliest, $currency),
            periods: $tracking
                ? $this->received->forPeriods($ranges, $earliest, $currency)
                : $this->value->forPeriods($ranges, $earliest, $today, $currency),
            bookedAheadMinor: $ahead['minor'],
            bookedAheadCount: $ahead['count'],
            provisionalMinor: $provisional['minor'],
            provisionalCount: $provisional['count'],
            owedMinor: $owed === null ? null : $owed['minor'],
            owedCount: $owed === null ? null : $owed['count'],
            snoozedMinor: $invoicing ? $this->snoozed($live, $today, $currency) : null,
            outstanding: $invoicing ? $this->outstandingTotal($today) : null,
        );
    }

    /**
     * Decision 27's headline, summed from the attention rows themselves.
     *
     * **Not a second query** (decision 2026-09-06.1954). The row is a task with
     * a name on it and this is the size of the problem, and the day they
     * disagree is the day the screen shows two rows and a total that does not
     * match them.
     *
     * **Summed before the cap**, from every client_balance row rather than the
     * ones that survived it. The two rules can collide, because the cap could
     * in principle drop a client_balance row on an account holding a hundred
     * more urgent ones, and this is the side to be wrong on: the figure is the
     * size of the problem rather than the size of the visible list, and
     * meta.attention.truncated is what tells the screen the rows under it are
     * not all of them.
     *
     * @param  array<int, AttentionRow>  $rows
     * @return array{minor: int, count: int}
     */
    private function owed(array $rows): array
    {
        $balances = array_filter(
            $rows,
            fn (AttentionRow $row) => $row->waitingOn === WaitingOn::ClientBalance,
        );

        $minor = 0;

        foreach ($balances as $row) {
            // The row's own figure, which is the booking's total overdue
            // balance across every invoice on it (decision 2026-09-06.2212).
            // Reading the same method the resource renders is what makes the
            // headline and the sum of the rows agree by construction: they
            // cannot diverge, because there is only one sum.
            $minor += $row->outstandingMinor() ?? 0;
        }

        return ['minor' => $minor, 'count' => count($balances)];
    }

    /**
     * The late balance money a snooze has taken out of the headline.
     *
     * **Decision 27's own reason for existing.** That decision says an artist
     * who can only stop the chasers by marking an invoice paid will mark it
     * paid, and the earnings figures then quietly become wrong. The snooze is
     * the honest escape hatch, and one that silently shrinks the headline
     * teaches artists to distrust the headline instead.
     *
     * It reads the same live bookings the attention rows came from, so it costs
     * no query and cannot count a lost or cancelled booking the headline could
     * never have held.
     *
     * It totals invoices, and so does the headline now that a row's money is
     * the booking's (decision 2026-09-06.2212), so the two are the same shape:
     * this is the money that would have been in the headline had nobody
     * snoozed it.
     *
     * @param  Collection<int, Booking>  $live
     */
    private function snoozed(Collection $live, CarbonImmutable $today, string $currency): int
    {
        $minor = 0;

        foreach ($live as $booking) {
            if ($booking->currency !== $currency) {
                continue;
            }

            foreach ($booking->invoices as $invoice) {
                if ($invoice->balanceIsPastDue($today) && $invoice->isSnoozed($today)) {
                    $minor += $invoice->outstandingMinor();
                }
            }
        }

        return $minor;
    }

    /**
     * Everything invoiced and not paid, split by whether it is late.
     *
     * Its own query rather than a sum over the attention rows, and the two
     * answer different questions: a booking with an invoice due next month is
     * outstanding and is waiting on nobody, so it has no row. That is why this
     * figure is larger than the headline above and why overdue_minor is usually
     * larger than owed_minor: an overdue deposit is late money that is not a
     * balance.
     */
    private function outstandingTotal(CarbonImmutable $today): OutstandingSplit
    {
        $invoices = Invoice::query()
            ->whereHas('booking', fn ($query) => $query->where('currency', $this->account->require()->currency))
            ->with(['payments', 'payments.booking'])
            ->get();

        return $this->outstanding->total($invoices, $today);
    }

    /**
     * Every feature key, resolved for this account, the same shape
     * GET /api/me sends.
     *
     * @return array<string, bool>
     */
    private function features(Account $account): array
    {
        return collect(FeatureKey::cases())
            ->mapWithKeys(fn (FeatureKey $key) => [$key->value => $this->features->enabled($account, $key)])
            ->all();
    }
}

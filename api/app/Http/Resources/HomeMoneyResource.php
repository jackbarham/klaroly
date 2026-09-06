<?php

namespace App\Http\Resources;

use App\Support\MoneySummary;
use App\Support\PeriodTotals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The home screen's money block, business logic 18.3.
 *
 * Every key is always present. A figure a toggle has taken away is null rather
 * than missing, so the screen reads a value rather than testing for a key, and
 * meta.features says why it is null. That is what makes it impossible to draw
 * "Received: £0" on an account that records no payments: with payment tracking
 * off the basis is booking_value and the label is the screen's to choose from
 * it.
 *
 * @property-read MoneySummary $resource
 */
class HomeMoneyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $money = $this->resource;
        $outstanding = $money->outstanding;

        return [
            'currency' => $money->currency,
            // 'payments' or 'booking_value': what the period figures below are
            // counting. Said rather than inferred from an empty key.
            'basis' => $money->basis,
            // Whether work in another currency has been left out of all of it.
            // Schema section 8 lets a money tile be filtered to the account
            // currency rather than grouped by it, and this is what stops that
            // being a silent lie on an account with a job abroad.
            'excludes_other_currencies' => $money->excludesOtherCurrencies,

            // Decision 27's headline: money earned and not collected. It is the
            // sum of the client_balance attention rows and not a second query
            // (decision 2026-09-06.1954), so the total and the rows under it
            // cannot disagree. Null when invoicing is off, because nothing was
            // ever given a due date.
            'owed_minor' => $money->owedMinor,
            // How many weddings that is, which the caption under the figure
            // names. The count of those same rows.
            'owed_count' => $money->owedCount,
            // What a snooze took out of the figure above, so the escape hatch
            // cannot make money vanish without saying so (decision 27). It is
            // a subset of outstanding.overdue_minor below, never something to
            // add to it: a snoozed invoice is still late, the artist has just
            // asked to stop hearing about it.
            'snoozed_minor' => $money->snoozedMinor,

            // As of today, never governed by the period selector: this is what
            // is unpaid right now. overdue_minor is not the same figure as
            // owed_minor above and is usually larger, because an overdue
            // deposit is late money that is not a balance.
            'outstanding' => $outstanding === null ? null : [
                'due_minor' => $outstanding->dueMinor,
                'overdue_minor' => $outstanding->overdueMinor,
                // A subset of the figure above rather than a third bucket:
                // due + overdue is the whole of outstanding, and this names the
                // part of overdue nobody is being chased for.
                'snoozed_minor' => $outstanding->snoozedMinor,
            ],

            // Also as of today. Provisional is never added to booked ahead
            // (business logic 18.3): a held date is not money, and a figure
            // that mixed the two would be the most optimistic number in the app.
            'booked_ahead_minor' => $money->bookedAheadMinor,
            'booked_ahead_count' => $money->bookedAheadCount,
            'provisional_minor' => $money->provisionalMinor,
            'provisional_count' => $money->provisionalCount,

            // All four at once rather than one behind a parameter. The screen's
            // selector then works with no signal, which is what one payload was
            // for (business logic 23.2), and the endpoint's cost stops being
            // something its caller sets.
            'periods' => array_map(
                fn (PeriodTotals $totals) => [
                    'from' => $totals->range->from->format('Y-m-d'),
                    'to' => $totals->range->to->format('Y-m-d'),
                    'value_minor' => $totals->valueMinor,
                    'booking_count' => $totals->bookingCount,
                    'average_value_minor' => $totals->averageValueMinor(),
                ],
                $money->periods,
            ),
        ];
    }
}

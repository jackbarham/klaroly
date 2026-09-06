<?php

namespace App\Http\Resources;

use App\Support\HomeSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The whole home screen in one payload, business logic section 18.
 *
 * **One endpoint rather than three**, and the reason is stronger than caching.
 * Decision 2026-09-06.1954 makes the owed headline the sum of the
 * client_balance attention rows, so three routes would mean either the money
 * one recomputing every booking's waiting-on state to produce one number, or
 * the client summing the rows itself and holding the definition of a money
 * figure in the front end. Business logic 23.2, that Home is the screen which
 * most has to work with no signal, is the second reason and not the first.
 *
 * @property-read HomeSummary $resource
 */
class HomeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Ordered by decision 217's precedence, most urgent first. The
            // screen groups by party afterwards and each group is the surviving
            // subsequence of this, so there is no second order to keep in step.
            'attention' => AttentionResource::collection($this->resource->attention),
            // Soonest first, from today forward.
            'upcoming' => UpcomingEventResource::collection($this->resource->upcoming),
            'money' => new HomeMoneyResource($this->resource->money),
        ];
    }

    /**
     * The meta block: what this response was computed against.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $summary = $this->resource;

        return [
            'meta' => [
                // Every FeatureKey, resolved for this account. The presence or
                // absence of a money figure follows from these rather than
                // being something the screen has to infer, and the attention
                // block's suppressions are explained by them too.
                'features' => $summary->features,
                // The ceiling-and-meta pattern GET /api/contacts and
                // GET /api/enquiries already carry. `total` is the count before
                // the cap, which is also what the screen's "See all 8" says, so
                // a capped list never hides how much there is.
                'attention' => [
                    'total' => $summary->attentionTotal,
                    'returned' => count($summary->attention),
                    'truncated' => $summary->attentionTruncated(),
                ],
                // The artist's own calendar day, and the zone it was worked out
                // in. Every detail line on this screen is a day count the
                // client computes with differenceInCalendarDays, and a phone in
                // another timezone computes a different one from the same
                // instant than the server used to decide what is overdue. These
                // two are what make the arithmetic reconcilable.
                'today' => $summary->today->format('Y-m-d'),
                'timezone' => $summary->timezone,
            ],
        ];
    }
}

<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything GET /api/home answers with: the three blocks of business logic
 * section 18, plus what the response was computed against.
 *
 * One object rather than the controller handing a resource four arguments,
 * because the meta block has to describe the same computation the blocks came
 * from. $attentionTotal in particular is the count BEFORE the cap, and it only
 * means anything beside the rows that survived it.
 */
final class HomeSummary
{
    /**
     * @param  array<int, AttentionRow>  $attention  already capped
     * @param  Collection<int, Event>  $upcoming
     * @param  array<string, bool>  $features  every FeatureKey, resolved
     */
    public function __construct(
        public readonly array $attention,
        public readonly int $attentionTotal,
        public readonly Collection $upcoming,
        public readonly MoneySummary $money,
        public readonly array $features,
        public readonly CarbonImmutable $today,
        public readonly string $timezone,
    ) {}

    /**
     * Whether the cap took anything off the attention block.
     */
    public function attentionTruncated(): bool
    {
        return $this->attentionTotal > count($this->attention);
    }
}

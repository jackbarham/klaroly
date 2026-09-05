<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

/**
 * The window GET /api/events answers for.
 *
 * `from` defaults to today rather than being unbounded, so a five-year-old
 * account does not ship its whole history to draw this week. Past work is
 * reachable by asking for it, which is the only reason the window exists.
 *
 * `to` has no default: the first call the app makes is today with no upper
 * bound, and that is deliberate. The list groups upcoming work into this
 * week, this month, next three months and later, and "later" cannot be
 * computed from a subset.
 */
class EventIndexRequest extends BaseRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->to() !== null && $this->to()->lessThan($this->from())) {
                    $validator->errors()->add('to', __('bookings.range_backwards'));

                    return;
                }

                // Only when both ends are given: an omitted `to` is unbounded
                // on purpose, and the row cap is what guards that call.
                if ($this->to() === null) {
                    return;
                }

                if ($this->from()->diffInDays($this->to()) > config('bookings.max_span_days')) {
                    $validator->errors()->add('to', __('bookings.range_too_wide', [
                        'days' => config('bookings.max_span_days'),
                    ]));
                }
            },
        ];
    }

    public function from(): CarbonImmutable
    {
        $from = $this->input('from');

        return $from === null ? CarbonImmutable::today() : CarbonImmutable::parse($from)->startOfDay();
    }

    public function to(): ?CarbonImmutable
    {
        $to = $this->input('to');

        return $to === null ? null : CarbonImmutable::parse($to)->startOfDay();
    }
}

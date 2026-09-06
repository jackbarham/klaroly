<?php

namespace App\Http\Requests;

use App\Enums\BookingStage;
use App\Enums\LostReason;
use App\Models\Booking;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * What PATCH /api/enquiries/{booking} accepts: a stage, and a reason when that
 * stage is lost.
 *
 * **It is not a general booking update.** Nothing else is named here, the
 * controller reads only validated(), and dates, prices and the contact are
 * reached by their own routes when those exist.
 *
 * Both rules are built from the enums rather than from a list of values typed
 * a second time, which is the same rule the check constraints follow: the
 * settable stages are Booking::SETTABLE_STAGES, and widening that constant
 * widens this request without anybody remembering to.
 */
class UpdateEnquiryStageRequest extends BaseRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'stage' => [
                'required',
                Rule::enum(BookingStage::class)->only(Booking::SETTABLE_STAGES),
            ],
            'lost_reason' => [
                // nullable so that a client which always sends both fields and
                // puts null in the second when there is no reason is saying
                // something true rather than making a mistake. required_if and
                // prohibited_unless are implicit rules and still run on a null.
                'nullable',
                'required_if:stage,'.BookingStage::Lost->value,
                // prohibited_unless rather than missing_unless: an actual
                // reason on any other stage is refused, and an explicit null is
                // not, because a null is not a reason.
                'prohibited_unless:stage,'.BookingStage::Lost->value,
                Rule::enum(LostReason::class),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        // Keys, never literals. Laravel's own validation.enum and
        // validation.required_if would answer in the framework's wording
        // rather than in words about enquiries.
        return [
            'stage.enum' => __('bookings.stage_not_settable'),
            'lost_reason.required_if' => __('bookings.lost_reason_required'),
            'lost_reason.prohibited_unless' => __('bookings.lost_reason_not_allowed'),
            'lost_reason.enum' => __('bookings.lost_reason_unknown'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $booking = $this->route('booking');

                if (! $booking instanceof Booking) {
                    return;
                }

                if (in_array($booking->stage, Booking::SETTABLE_STAGES, true)) {
                    return;
                }

                // **A 422 and not a 403**, which is the difference between the
                // caller and the target: whoever is asking is allowed to be
                // here, and the record they are pointing at is the wrong kind.
                // A confirmed job moved through a route built for a list of
                // maybes is a downgrade of something signed.
                //
                // It hangs off `stage` because that is the only field this
                // request has, so the field is where the message renders rather
                // than a claim that the value sent was invalid. The message
                // says what is actually wrong. EventController::refuseIfTooMany
                // puts a range-size error on `from` for the same reason.
                $validator->errors()->add('stage', __('bookings.not_an_enquiry'));
            },
        ];
    }

    public function stage(): BookingStage
    {
        return BookingStage::from($this->validated('stage'));
    }

    public function lostReason(): ?LostReason
    {
        $reason = $this->validated('lost_reason');

        return $reason === null ? null : LostReason::from($reason);
    }
}

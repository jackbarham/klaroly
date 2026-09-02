<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * Answers a failed forgot-password request exactly as a successful one is
 * answered, so an unknown address cannot be told apart from a known one
 * (technical proposal section 5, gap 1). Fortify's own failed response would
 * return a validation error naming the email field.
 *
 * Fortify constructs this with the broker's status string. It is ignored on
 * purpose: the body always carries the "sent" message.
 */
class UniformPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(protected string $status) {}

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['message' => trans(Password::RESET_LINK_SENT)], 200);
    }
}

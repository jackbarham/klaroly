<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse;

/**
 * Mobile twin of Fortify's POST /email/verification-notification (decision
 * 87). Fortify's route needs a web session; this one accepts either a bearer
 * token or the session cookie. The answers are Fortify's: 204 when the
 * address is already verified, 202 once the email has been sent.
 */
class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): JsonResponse|EmailVerificationNotificationSentResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return new JsonResponse('', 204);
        }

        $request->user()->sendEmailVerificationNotification();

        return app(EmailVerificationNotificationSentResponse::class);
    }
}

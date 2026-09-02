<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

/**
 * Mobile twin of Fortify's POST /forgot-password (decision 87). The link is
 * sent through the same password broker and the answer comes from the same
 * two response bindings, so a known and an unknown address get the same
 * body here as they do on the web route.
 */
class PasswordResetLinkController extends Controller
{
    public function store(Request $request): Responsable
    {
        $request->validate([
            Fortify::email() => ['required', 'email'],
        ]);

        $status = Password::broker(config('fortify.passwords'))->sendResetLink(
            $request->only(Fortify::email()),
        );

        return $status === Password::RESET_LINK_SENT
            ? app(SuccessfulPasswordResetLinkRequestResponse::class, ['status' => $status])
            : app(FailedPasswordResetLinkRequestResponse::class, ['status' => $status]);
    }
}

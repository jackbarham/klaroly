<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Fortify;

/**
 * Mobile twin of Fortify's POST /reset-password (decision 87). The broker
 * checks the token and the same ResetUserPassword action changes the
 * password and revokes every token and session. Unlike Fortify's controller
 * it does not log the user into the web guard afterwards; the phone signs
 * in again with the new password.
 */
class NewPasswordController extends Controller
{
    public function store(Request $request): Responsable
    {
        $request->validate([
            'token' => ['required'],
            Fortify::email() => ['required', 'email'],
            'password' => ['required'],
        ]);

        $status = Password::broker(config('fortify.passwords'))->reset(
            $request->only(Fortify::email(), 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                app(ResetsUserPasswords::class)->reset($user, $request->all());
            },
        );

        return $status === Password::PASSWORD_RESET
            ? app(PasswordResetResponse::class, ['status' => $status])
            : app(FailedPasswordResetResponse::class, ['status' => $status]);
    }
}

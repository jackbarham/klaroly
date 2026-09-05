<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lowercases and trims the email input before anything reads it (decision
 * 84). Applied to every Fortify route and to the mobile token endpoint, so
 * login, registration, password reset and profile update all see the same
 * value. The lower(email) index on users is the backstop, not the mechanism.
 *
 * normalise() is the one definition of what a normalised address is. The
 * actions and the credential check call it again on their own input, so
 * they stay safe when called from outside an HTTP request.
 */
class NormaliseEmail
{
    public static function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => self::normalise($email)]);
        }

        return $next($request);
    }
}

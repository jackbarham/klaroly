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
 */
class NormaliseEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => mb_strtolower(trim($email))]);
        }

        return $next($request);
    }
}

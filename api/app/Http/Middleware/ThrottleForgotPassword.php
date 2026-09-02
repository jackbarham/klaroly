<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the "forgot-password" rate limiter to Fortify's password.email
 * route. Fortify only lets config name the login, two-factor, passkey and
 * verification limiters, so this sits in the Fortify route group and does
 * nothing on every other route.
 */
class ThrottleForgotPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route()?->getName() !== 'password.email') {
            return $next($request);
        }

        return app(ThrottleRequests::class)->handle($request, $next, 'forgot-password');
    }
}

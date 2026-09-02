<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AccountResolver;
use App\Services\TokenIssuer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Mobile twin of Fortify's POST /register (decision 87). The same action
 * creates the user, account, settings, membership and username history, and
 * the same event sends the verification email. The difference is what comes
 * back: no session is started, and a personal access token is issued instead,
 * exactly as POST /api/auth/token would issue one.
 */
class RegisterController extends Controller
{
    public function store(
        RegisterRequest $request,
        CreatesNewUsers $creator,
        AccountResolver $resolver,
        TokenIssuer $issuer,
    ): JsonResponse {
        $user = $creator->create($request->all());

        event(new Registered($user));

        // The model returned by the action only holds what was written to
        // it. Reading it back picks up the column defaults, such as the
        // empty notification preferences, so "me" here matches GET /api/me.
        $user->refresh();

        // Binds the new account as the current one so MeResource can read it.
        $resolver->resolve($user);

        $token = $issuer->issue($user, $request->input('device_name'));

        return response()->json($issuer->payload($token, $user, $request), 201);
    }
}

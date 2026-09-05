<?php

namespace App\Http\Controllers;

use App\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Stateless twin of Fortify's PUT /user/profile-information (decision 87),
 * at the same path under /api. The path is deliberately the same: a twin is
 * the same route without the session, and a twin with a different name
 * invites the question of whether it also behaves differently.
 *
 * This controller, AccountController and MarketingConsentController sit
 * beside MeController rather than under Auth, because all three write what
 * MeController reads and all three answer with its payload. Changing a
 * password is under Auth because it revokes credentials.
 */
class ProfileInformationController extends Controller
{
    public function update(Request $request, UpdatesUserProfileInformation $updater): JsonResponse
    {
        // Fortify's own action, resolved from the container, so the web
        // route and this one validate the same way and a changed email
        // un-verifies the address and sends a fresh verification email
        // exactly once, in one place.
        $updater->update($request->user(), $request->all());

        // A model written to does not carry what the database put there
        // (decision 95), so it is read back before the payload is built.
        $request->user()->refresh();

        return MeResource::make($request->user())->response()->setStatusCode(200);
    }
}

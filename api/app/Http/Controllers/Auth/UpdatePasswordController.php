<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * Stateless twin of Fortify's PUT /user/password (decision 87), at the same
 * path under /api. NewPasswordController is the other half of the same
 * subject: that one is the reset, reached from an emailed link by someone
 * who cannot sign in, and this one is the change, made by someone who can.
 *
 * The action revokes every other token and session and keeps the credential
 * that made this request, so the phone does not sign itself out. Nothing in
 * the me payload changes, so none is returned.
 */
class UpdatePasswordController extends Controller
{
    public function update(Request $request, UpdatesUserPasswords $updater): JsonResponse
    {
        $updater->update($request->user(), $request->all());

        return response()->json(['message' => __('auth.password_updated')]);
    }
}

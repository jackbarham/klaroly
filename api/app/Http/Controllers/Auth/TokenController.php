<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\IssueTokenRequest;
use App\Http\Resources\TokenResource;
use App\Services\AccountResolver;
use App\Services\PasswordAuthenticator;
use App\Services\TokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal access tokens for the mobile app (decision 84). The web app uses
 * the session cookie and Fortify's /login and /logout instead, but a session
 * caller may still list and revoke tokens, so the devices screen works on
 * both.
 */
class TokenController extends Controller
{
    /**
     * Issue a token for a device. Credentials are checked the same way the
     * web login checks them, and a wrong email or password gets the same
     * answer.
     */
    public function store(
        IssueTokenRequest $request,
        PasswordAuthenticator $authenticator,
        AccountResolver $resolver,
        TokenIssuer $issuer,
    ): JsonResponse {
        $user = $authenticator->attempt($request->input('email'), $request->input('password'));

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($resolver->resolve($user) === null) {
            return response()->json(['message' => __('account.no_membership')], 403);
        }

        $token = $issuer->issue($user, $request->input('device_name'));

        return response()->json($issuer->payload($token, $user, $request));
    }

    /**
     * Every token the caller holds, with the one making this request marked.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return TokenResource::collection($request->user()->tokens()->orderBy('id')->get());
    }

    /**
     * Revoke one of the caller's own tokens. Anyone else's is a 404.
     */
    public function destroy(Request $request, int $id): Response
    {
        $request->user()->tokens()->findOrFail($id)->delete();

        return response()->noContent();
    }

    /**
     * Revoke the token that made this request. A session cookie is not a
     * token, so a web caller is told to use /logout instead.
     */
    public function destroyCurrent(Request $request): Response|JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return response()->json(['message' => __('auth.session_not_token')], 400);
        }

        $token->delete();

        return response()->noContent();
    }
}

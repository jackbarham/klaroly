<?php

namespace App\Services;

use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\NewAccessToken;

/**
 * The one place a personal access token is minted (decision 87). The token
 * endpoint and the mobile registration twin both come here, so a token has
 * the same name, abilities and expiry whichever route issued it, and both
 * answer with the same {token, expires_at, me} shape.
 *
 * The current account must be bound before payload() is called, because
 * MeResource reads it.
 */
class TokenIssuer
{
    public function issue(User $user, string $deviceName): NewAccessToken
    {
        return $user->createToken(
            $deviceName,
            ['*'],
            now()->addDays(config('sanctum.token_expiry_days')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(NewAccessToken $token, User $user, Request $request): array
    {
        return [
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'me' => MeResource::make($user)->resolve($request),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One personal access token as the devices list shows it. The plain-text
 * token is never here; it is shown once, when it is issued.
 *
 * @mixin PersonalAccessToken
 */
class TokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $current = $request->user()->currentAccessToken();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'current' => $current instanceof PersonalAccessToken && $current->id === $this->id,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Rules\Username;
use Illuminate\Http\JsonResponse;

class UsernameAvailabilityController extends Controller
{
    /**
     * Whether a username can be claimed, with a reason code the app
     * translates itself. The same rule registration runs, so the two agree.
     */
    public function __invoke(string $username): JsonResponse
    {
        $reason = (new Username)->reasonFor($username);

        return response()->json([
            'available' => $reason === null,
            'reason' => $reason,
        ]);
    }
}

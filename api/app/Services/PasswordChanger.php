<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The one place a password is replaced (technical proposal section 5, gap
 * 3). Both Fortify actions come here, so a reset through an emailed link and
 * a change from the settings screen revoke the same things and send the same
 * email, whichever route, web or mobile twin, asked for it.
 *
 * Every token and every session goes, except the credential making the
 * change when there is one. A reset has none: it is asked for by someone who
 * could not sign in. A change from the settings screen has one, and which
 * kind it is depends on the caller, because a browser holds a session and
 * the phone holds a token. Keeping the wrong one, or neither, signs the
 * person out of the device they are standing in front of.
 */
class PasswordChanger
{
    public function change(
        User $user,
        string $password,
        ?string $keepSessionId = null,
        ?int $keepTokenId = null,
    ): void {
        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        $user->tokens()
            ->when($keepTokenId !== null, fn ($query) => $query->where('id', '!=', $keepTokenId))
            ->delete();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();

        $user->notify(new PasswordChanged);
    }
}

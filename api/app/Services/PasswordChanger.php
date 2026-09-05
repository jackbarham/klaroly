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
 * Every token goes. Every session goes too, except the one making the
 * change when there is one: a reset has no current session, a change from
 * the settings screen does.
 */
class PasswordChanger
{
    public function change(User $user, string $password, ?string $keepSessionId = null): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        $user->tokens()->delete();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();

        $user->notify(new PasswordChanged);
    }
}

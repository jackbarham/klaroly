<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The one place an email and password are checked. Fortify's login route
 * and the mobile token endpoint both call it, so the two cannot drift.
 *
 * Returns the user or null and never says which of the two was wrong.
 */
class PasswordAuthenticator
{
    public function attempt(string $email, string $password): ?User
    {
        // Email is stored lowercase and the unique index is on lower(email),
        // so comparing lower(email) uses that index and is safe even for a
        // row written before normalisation existed.
        $user = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->first();

        // A user who signed in with a provider may have no password at all.
        if ($user === null || $user->password === null) {
            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
}

<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\PasswordChanger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly PasswordChanger $changer) {}

    /**
     * Validate and reset the user's forgotten password, then sign every
     * device and browser out. There is no current session on a reset, so
     * every session row goes.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $this->changer->change($user, $input['password']);
    }
}

<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\PasswordChanger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly PasswordChanger $changer) {}

    /**
     * Validate and update the user's password, then sign every other device
     * and browser out. The session making the change is kept.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('auth.current_password_mismatch'),
        ])->validateWithBag('updatePassword');

        // Outside an HTTP request there is no current session to keep.
        $currentSessionId = request()->hasSession() ? request()->session()->getId() : null;

        $this->changer->change($user, $input['password'], $currentSessionId);
    }
}

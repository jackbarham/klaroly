<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\PasswordChanger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Sanctum\PersonalAccessToken;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly PasswordChanger $changer) {}

    /**
     * Validate and update the user's password, then sign every other device
     * and browser out. The credential making the change is kept: the session
     * on the web, the token on the phone.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', $this->matchesCurrentPassword($user)],
            'password' => $this->passwordRules(),
        ])->validateWithBag('updatePassword');

        // Outside an HTTP request there is neither a session nor a token to
        // keep, so both of these are null and everything is revoked.
        $request = request();

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        $currentToken = $request->user()?->currentAccessToken();

        // Named, because the two "keep" parameters are consecutive and
        // nullable, and nothing in this codebase declares strict types, so a
        // transposed pair would coerce rather than fail.
        $this->changer->change(
            $user,
            $input['password'],
            keepSessionId: $currentSessionId,
            keepTokenId: $currentToken instanceof PersonalAccessToken ? $currentToken->id : null,
        );
    }

    /**
     * Check the password given against the user this action was handed.
     *
     * Laravel's current_password rule asks a named guard for its user, and
     * the web guard is a guest on a bearer-token request, so the rule would
     * reject every password change made from the phone. Checking the user
     * directly is the same question without the guard, and it answers the
     * same way for a session caller, a token caller and a caller with no
     * request at all.
     */
    private function matchesCurrentPassword(User $user): callable
    {
        return function (string $attribute, mixed $value, callable $fail) use ($user) {
            // Nullable for the provider sign-ins that come later. Someone
            // with no password cannot be asked to confirm it.
            if ($user->password === null) {
                $fail(__('auth.no_password_set'));

                return;
            }

            if (! Hash::check((string) $value, $user->password)) {
                $fail(__('auth.current_password_mismatch'));
            }
        };
    }
}

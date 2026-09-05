<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\UniformPasswordResetLinkResponse;
use App\Models\User;
use App\Services\PasswordAuthenticator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // An unknown address gets the same answer as a known one.
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, UniformPasswordResetLinkResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // The same check the mobile token endpoint runs, so the two agree.
        Fortify::authenticateUsing(function (Request $request) {
            return app(PasswordAuthenticator::class)->attempt(
                (string) $request->input(Fortify::username()),
                (string) $request->input('password'),
            );
        });

        // The reset link opens the web app, which posts the token back to
        // POST /reset-password on this API.
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return config('app.frontend_url').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->email,
            ]);
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($this->emailAndIpKey($request));
        });

        // Twin of the login limiter for the mobile token endpoint.
        RateLimiter::for('token', function (Request $request) {
            return Limit::perMinute(5)->by($this->emailAndIpKey($request));
        });

        // The mobile registration twin, keyed on IP alone because there is
        // no account to key on yet. Fortify's /register has no limiter.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Applied to Fortify's password.email route by ThrottleForgotPassword
        // and directly to the mobile forgot-password twin.
        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perMinute(3)->by($this->emailAndIpKey($request));
        });

        // Both settings writes that cost something: a profile update can
        // queue a verification email, and a password update revokes
        // credentials. Keyed on the user because both routes are
        // authenticated, with the IP as the fallback, because a limiter is a
        // global registration and a later route could apply it before auth.
        RateLimiter::for('profile-update', function (Request $request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('password-update', function (Request $request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }

    /**
     * Lowercased email plus IP, the key every credential limiter shares.
     */
    private function emailAndIpKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());
    }
}

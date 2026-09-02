<?php

namespace App\Providers;

use App\Models\Account;
use App\Support\CurrentAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant per request or job, shared by every scoped model.
        $this->app->singleton(CurrentAccount::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every date the framework hands back is immutable, so a value read
        // from a model cannot be changed by accident somewhere else.
        Date::use(CarbonImmutable::class);

        // The account, not the user, is the billable entity.
        Cashier::useCustomerModel(Account::class);

        // The one password policy (technical proposal section 5, gap 5).
        // The breach check needs the network, which the test suite does not
        // have, so it is left off there.
        Password::defaults(function () {
            $rule = Password::min(10);

            return $this->app->environment('testing') ? $rule : $rule->uncompromised();
        });
    }
}

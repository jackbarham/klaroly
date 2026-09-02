<?php

namespace App\Providers;

use App\Models\Account;
use App\Support\CurrentAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
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
    }
}

<?php

namespace App\Providers;

use App\Models\Account;
use App\Support\CurrentAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
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

        // A fillable model handed an attribute it does not list drops it in
        // silence, so a misspelt key in a create() call is a column left null
        // with no error anywhere. Outside production that is an exception
        // instead, so it fails where it is written rather than on a screen
        // nobody was looking at. Seeders are not affected: db:seed runs them
        // unguarded, which is why the demo seeder may set created_at.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // The account, not the user, is the billable entity.
        Cashier::useCustomerModel(Account::class);

        // Replies reach the routed inbox, not the sending subdomain.
        Mail::alwaysReplyTo('hello@klaroly.com', 'Klaroly');

        // The one password policy (technical proposal section 5, gap 5).
        // The breach check needs the network, which the test suite does not
        // have, so it is left off there.
        Password::defaults(function () {
            $rule = Password::min(10);

            return $this->app->environment('testing') ? $rule : $rule->uncompromised();
        });
    }
}

<?php

use App\Http\Middleware\BindCurrentAccount;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lets the web app authenticate against routes/api.php with its
        // session cookie. The mobile app sends a bearer token instead and
        // is unaffected by this.
        $middleware->statefulApi();

        // Every authenticated API route runs ['auth:sanctum', 'account'], so
        // the scoped models have a tenant before any controller runs.
        $middleware->alias([
            'account' => BindCurrentAccount::class,
        ]);

        // **Route-model binding on a scoped model needs the tenant first.**
        // SubstituteBindings comes from the api group and BindCurrentAccount
        // from the route, so without this line the stack is auth, bindings,
        // account: the binding query runs with no account bound, the global
        // scope in BelongsToAccount turns into `where 1 = 0`, and every
        // {booking} and {contact} route answers 404 for rows the caller owns.
        //
        // The failure is worse than the 404, which is why this is a line of
        // configuration rather than a lookup written out in each controller.
        // A tenancy test asserting that another account's row is not found
        // passes just as happily when nothing is ever found, so it cannot tell
        // the fix from the bug. Anything relying on it has to assert the
        // caller's own row IS found in the same test.
        $middleware->prependToPriorityList(SubstituteBindings::class, BindCurrentAccount::class);

        // A browser that reaches a protected route while logged out is sent
        // to the web app's login page, not to a route this API does not have.
        // API requests get a JSON 401 instead (see shouldRenderJsonWhen).
        $middleware->redirectGuestsTo(fn () => config('app.frontend_url').'/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

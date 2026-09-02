<?php

use App\Http\Middleware\BindCurrentAccount;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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

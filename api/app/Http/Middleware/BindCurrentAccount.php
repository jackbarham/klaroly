<?php

namespace App\Http\Middleware;

use App\Services\AccountResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's account as the current tenant, so every
 * scoped model returns that account's rows. Registered under the alias
 * "account" and applied after auth:sanctum on every authenticated API route.
 *
 * A user with no membership at all gets a 403, not an exception.
 */
class BindCurrentAccount
{
    public function __construct(private readonly AccountResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = $this->resolver->resolve($request->user());

        if ($account === null) {
            return response()->json(['message' => __('account.no_membership')], 403);
        }

        return $next($request);
    }
}

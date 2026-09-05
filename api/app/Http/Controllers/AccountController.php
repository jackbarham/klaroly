<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\MeResource;
use App\Support\CurrentAccount;
use Illuminate\Http\JsonResponse;

/**
 * The business name, which is the only thing on the account the My Account
 * screen can change. Who is allowed to change it is the request's job.
 */
class AccountController extends Controller
{
    public function update(UpdateAccountRequest $request, CurrentAccount $currentAccount): JsonResponse
    {
        // The bound instance, not a fresh query, because MeResource reads
        // that same object and would otherwise answer with the old name.
        //
        // The username is not touched here. It is derived from the business
        // name once, at registration, and after that the two are separate
        // facts: a rename is a display change, and a username change would
        // move a public profile address and write a username_history row.
        $account = $currentAccount->require();

        $account->update(['name' => $request->validated('name')]);

        $account->refresh();

        return MeResource::make($request->user())->response()->setStatusCode(200);
    }
}

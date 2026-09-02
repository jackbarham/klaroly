<?php

namespace App\Http\Controllers;

use App\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * The logged-in user, their current account, membership and features.
     * The account is already bound by the BindCurrentAccount middleware.
     *
     * Always 200. A resource answers 201 when its model was created during
     * the same process, which happens when registration and this call share
     * one test, and is never what a read should say.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return MeResource::make($request->user())->response()->setStatusCode(200);
    }
}

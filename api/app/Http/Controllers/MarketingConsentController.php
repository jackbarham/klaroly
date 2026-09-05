<?php

namespace App\Http\Controllers;

use App\Enums\MarketingConsentSource;
use App\Http\Requests\MarketingConsentRequest;
use App\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;

/**
 * Marketing consent is a dated fact, not a boolean (decision 71): the
 * timestamp is the consent and the source says which screen recorded it.
 */
class MarketingConsentController extends Controller
{
    public function update(MarketingConsentRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->boolean('consented')) {
            $user->forceFill([
                'marketing_consent_at' => now(),
                'marketing_consent_source' => MarketingConsentSource::Settings,
            ])->save();
        } else {
            // The source is deliberately left alone. Clearing the timestamp
            // withdraws the consent; the source stays as the record of where
            // the consent that is being withdrawn was given, which is what
            // makes it worth keeping at all.
            $user->forceFill([
                'marketing_consent_at' => null,
            ])->save();
        }

        // Read back before the payload is built (decision 95).
        $user->refresh();

        return MeResource::make($user)->response()->setStatusCode(200);
    }
}

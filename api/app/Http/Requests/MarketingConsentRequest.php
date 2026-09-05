<?php

namespace App\Http\Requests;

class MarketingConsentRequest extends BaseRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // Required rather than nullable, so a request that forgot the field
        // is a 422 and never a silent withdrawal of consent.
        return [
            'consented' => ['required', 'boolean'],
        ];
    }
}

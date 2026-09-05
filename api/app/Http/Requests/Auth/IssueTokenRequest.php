<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class IssueTokenRequest extends BaseRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:80'],
        ];
    }
}

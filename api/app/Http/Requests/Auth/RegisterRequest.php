<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

/**
 * The mobile registration twin. Only device_name is validated here; every
 * other field is validated by CreateNewUser, exactly as on POST /register.
 * Checking the device name first means a request without one creates
 * nothing.
 */
class RegisterRequest extends BaseRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:80'],
        ];
    }
}

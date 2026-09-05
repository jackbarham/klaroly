<?php

namespace App\Http\Requests;

class UpdateAccountRequest extends BaseRequest
{
    /**
     * Only an owner renames the business.
     */
    public function authorize(): bool
    {
        return $this->user()->currentMembership()?->isOwner() === true;
    }

    protected function deniedMessage(): string
    {
        return 'account.owner_only';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // 120 is the width of accounts.name.
        return [
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}

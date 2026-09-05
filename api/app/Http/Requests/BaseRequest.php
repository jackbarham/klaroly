<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The base every form request in this API extends.
 *
 * A form request whose authorize() returns false refuses with Laravel's own
 * hardcoded "This action is unauthorized", which is a user-facing string
 * written in English in the framework rather than a key in lang/en-GB. Doing
 * that once by hand is easy; remembering to do it on the fourth form request
 * nobody has written yet is not, which is why this is a base class and not a
 * line in CLAUDE.md.
 *
 * A request that refuses for a reason of its own overrides deniedMessage()
 * with its own key. Most requests never refuse at all, so the default is
 * there rather than made abstract.
 *
 * Not named Request, which would sit one namespace from Illuminate\Http\Request
 * and force an alias into any file importing both. Not FormRequest either,
 * which would need an alias in this file.
 */
abstract class BaseRequest extends FormRequest
{
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__($this->deniedMessage()));
    }

    /**
     * The translation key explaining why this request was refused.
     */
    protected function deniedMessage(): string
    {
        return 'common.not_allowed';
    }
}

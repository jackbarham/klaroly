<?php

namespace App\Models;

use App\Enums\IdentityProvider;
use Database\Factories\IdentityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A third-party sign-in identity. Not scoped to an account, because it
 * belongs to the person and is looked up before any account is chosen.
 */
#[Fillable(['user_id', 'provider', 'provider_user_id', 'provider_email', 'email_is_private'])]
class Identity extends Model
{
    /** @use HasFactory<IdentityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => IdentityProvider::class,
            'email_is_private' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

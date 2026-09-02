<?php

namespace App\Models;

use App\Enums\AccountRole;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\AccountUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership of an account. Permissions are the toggles here, never a third
 * role, and policies short-circuit on role = owner.
 */
#[Fillable(['account_id', 'user_id', 'role', 'can_edit', 'can_see_prices', 'can_see_invoices', 'can_see_contacts', 'invited_by_user_id', 'invited_at', 'accepted_at'])]
class AccountUser extends Model
{
    /** @use HasFactory<AccountUserFactory> */
    use BelongsToAccount, HasFactory;

    protected $table = 'account_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AccountRole::class,
            'can_edit' => 'boolean',
            'can_see_prices' => 'boolean',
            'can_see_invoices' => 'boolean',
            'can_see_contacts' => 'boolean',
            'invited_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isOwner(): bool
    {
        return $this->role === AccountRole::Owner;
    }
}

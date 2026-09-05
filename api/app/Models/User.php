<?php

namespace App\Models;

use App\Enums\MarketingConsentSource;
use App\Support\CurrentAccount;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person with a login, and only person things. Being an artist is an
 * account_user row; being a client is a contacts.user_id link. A user is never
 * a tenant, so this model is not scoped to an account.
 *
 * Implements MustVerifyEmail so that registration sends the verification
 * email. Verification is sent, not enforced (decision 83): no route checks
 * email_verified_at yet.
 */
#[Fillable(['name', 'email', 'password', 'notification_preferences', 'marketing_consent_at', 'marketing_consent_source', 'last_account_id'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'marketing_consent_at' => 'immutable_datetime',
            'marketing_consent_source' => MarketingConsentSource::class,
            'notification_preferences' => 'array',
            'password' => 'hashed',
        ];
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_user')
            ->withPivot(['role', 'can_edit', 'can_see_prices', 'can_see_invoices', 'can_see_contacts', 'accepted_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(AccountUser::class);
    }

    /**
     * This user's membership of the account bound in CurrentAccount, or null
     * when they are not a member of it.
     *
     * The account half of the question is answered by AccountUser's global
     * scope, so no caller names an account_id. That scope fails closed with
     * no account bound, which is right for a query and wrong here: the
     * answer becomes a permission decision, and a null meaning "no tenant
     * was bound" would be read as "not an owner" and refused with a message
     * about ownership. Requiring the account first means a missing tenant is
     * a loud failure naming itself, and null means one thing only.
     */
    public function currentMembership(): ?AccountUser
    {
        app(CurrentAccount::class)->require();

        return AccountUser::query()->where('user_id', $this->id)->first();
    }

    public function identities(): HasMany
    {
        return $this->hasMany(Identity::class);
    }

    public function lastAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'last_account_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}

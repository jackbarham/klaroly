<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

/**
 * The business. The tenant. Every customer-data table hangs off this one and
 * the account is the billable entity for Cashier.
 */
#[Fillable(['name', 'username', 'vertical', 'country', 'locale', 'currency', 'timezone', 'profile_enabled', 'trial_ends_at'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use Billable, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // Claiming a username writes a history row. Changing one releases the
        // old row and writes a new one. The unique index on
        // username_history.username is what stops a released name being
        // claimed by anyone else.
        static::created(function (Account $account) {
            $account->usernameHistory()->create([
                'username' => $account->username,
                'claimed_at' => now(),
            ]);
        });

        static::updated(function (Account $account) {
            if (! $account->wasChanged('username')) {
                return;
            }

            $account->usernameHistory()
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            $account->usernameHistory()->create([
                'username' => $account->username,
                'claimed_at' => now(),
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profile_enabled' => 'boolean',
            'trial_ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /**
     * Usernames are hostnames, so they are always stored in lower case.
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower(trim($value)),
        );
    }

    public function settings(): HasOne
    {
        return $this->hasOne(AccountSettings::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(AccountUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_user')
            ->withPivot(['role', 'can_edit', 'can_see_prices', 'can_see_invoices', 'can_see_contacts', 'accepted_at'])
            ->withTimestamps();
    }

    public function usernameHistory(): HasMany
    {
        return $this->hasMany(UsernameHistory::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function messageTemplates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function contractTemplates(): HasMany
    {
        return $this->hasMany(ContractTemplate::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }
}

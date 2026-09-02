<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\ServiceAppliesTo;
use App\Enums\ServiceKind;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A row on the rate card. Rows, never an enum: the artist can rename, reprice
 * or delete every one of them.
 */
#[Fillable(['account_id', 'name', 'description', 'kind', 'applies_to', 'price_minor', 'sort_order', 'active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToAccount, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ServiceKind::class,
            'applies_to' => ServiceAppliesTo::class,
            'price_minor' => MoneyCast::class,
            'active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BookingLine::class);
    }

    public function partyMembers(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }
}

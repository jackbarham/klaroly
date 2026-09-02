<?php

namespace App\Models;

use App\Enums\EntitlementSource;
use App\Enums\EntitlementStatus;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\EntitlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Who is allowed in, separately from who paid. Read only through
 * App\Services\Features once the billing prompt lands.
 */
#[Fillable(['account_id', 'source', 'external_id', 'plan_key', 'status', 'current_period_end'])]
class Entitlement extends Model
{
    /** @use HasFactory<EntitlementFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => EntitlementSource::class,
            'status' => EntitlementStatus::class,
            'current_period_end' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, [EntitlementStatus::Trialing, EntitlementStatus::Active], true);
    }
}

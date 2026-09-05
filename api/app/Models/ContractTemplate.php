<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Database\Factories\ContractTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versioned agreement wording by market and vertical. account_id null is the
 * system default. A version is never edited once it exists; a change is a
 * new row and the old one is retired.
 */
#[Fillable(['account_id', 'market', 'vertical', 'version', 'name', 'body', 'effective_from', 'retired_at'])]
class ContractTemplate extends Model
{
    /** @use HasFactory<ContractTemplateFactory> */
    use BelongsToAccount, HasFactory;

    protected static function includesSystemRows(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'immutable_date',
            'retired_at' => 'immutable_date',
        ];
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    public function isSystem(): bool
    {
        return $this->account_id === null;
    }
}

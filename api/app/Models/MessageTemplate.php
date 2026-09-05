<?php

namespace App\Models;

use App\Enums\TemplateKey;
use App\Enums\TemplateMode;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message template on one of three tiers: system (account_id null), account
 * override, or per-booking override (booking_id set). The tenancy scope shows
 * the current account's rows and the system rows together.
 */
#[Fillable(['account_id', 'booking_id', 'key', 'locale', 'vertical', 'name', 'subject', 'body', 'variants', 'enabled', 'mode', 'trigger', 'sort_order'])]
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
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
            'key' => TemplateKey::class,
            'variants' => 'array',
            'enabled' => 'boolean',
            'mode' => TemplateMode::class,
            'trigger' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isSystem(): bool
    {
        return $this->account_id === null;
    }
}

<?php

namespace App\Models;

use Database\Factories\UsernameHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every username an account has ever held. Carries account_id but is not
 * scoped to an account, because the hostname lookup that reads it runs before
 * any account context exists.
 */
#[Fillable(['account_id', 'username', 'claimed_at', 'released_at'])]
class UsernameHistory extends Model
{
    /** @use HasFactory<UsernameHistoryFactory> */
    use HasFactory;

    protected $table = 'username_history';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isCurrent(): bool
    {
        return $this->released_at === null;
    }
}

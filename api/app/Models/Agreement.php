<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\AgreementStatus;
use App\Enums\SignedMethod;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\AgreementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of the agreement on a booking. The rendered body is the exact
 * text the client saw, and a signed row is never edited.
 */
#[Fillable([
    'account_id', 'booking_id', 'contract_template_id', 'version', 'status', 'rendered_body', 'rendered_sha256',
    'pdf_path', 'total_minor', 'deposit_minor', 'sent_at', 'first_viewed_at', 'signed_at', 'signed_method',
    'signed_name', 'signed_ip', 'signed_user_agent', 'signed_note', 'superseded_by_id', 'created_by_user_id',
])]
class Agreement extends Model
{
    /** @use HasFactory<AgreementFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgreementStatus::class,
            'total_minor' => MoneyCast::class,
            'deposit_minor' => MoneyCast::class,
            'sent_at' => 'immutable_datetime',
            'first_viewed_at' => 'immutable_datetime',
            'signed_at' => 'immutable_datetime',
            'signed_method' => SignedMethod::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'superseded_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isSigned(): bool
    {
        return $this->status === AgreementStatus::Signed;
    }

    /**
     * The hash a body should carry, so a stored row can be checked against
     * its own text.
     */
    public static function hashBody(string $body): string
    {
        return hash('sha256', $body);
    }
}

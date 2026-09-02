<?php

namespace App\Models\Concerns;

use App\Models\Account;
use App\Support\CurrentAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Tenancy for every customer-data model.
 *
 * Applies a global scope that limits every query to the current account, and
 * fills account_id on create when the caller has not set it. With no current
 * account bound, scoped queries return nothing and creating throws, so a
 * missing tenant can never leak or misfile a row. Code that genuinely needs
 * to see across accounts, such as a scheduled job, says so explicitly with
 * withoutGlobalScope('account').
 */
trait BelongsToAccount
{
    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $query) {
            $accountId = app(CurrentAccount::class)->id();
            $column = $query->getModel()->qualifyColumn('account_id');

            if ($accountId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            if (static::includesSystemRows()) {
                $query->where(function (Builder $query) use ($column, $accountId) {
                    $query->where($column, $accountId)->orWhereNull($column);
                });

                return;
            }

            $query->where($column, $accountId);
        });

        static::creating(function (Model $model) {
            // array_key_exists rather than isset, so that a deliberate null
            // (a system row on a table that allows them) is left alone.
            if (array_key_exists('account_id', $model->getAttributes())) {
                return;
            }

            $accountId = app(CurrentAccount::class)->id();

            if ($accountId === null) {
                throw new RuntimeException(
                    'Cannot create '.static::class.' without a current account. Set App\Support\CurrentAccount first or pass account_id.'
                );
            }

            $model->setAttribute('account_id', $accountId);
        });
    }

    /**
     * Whether rows with a null account_id are system defaults that every
     * account can read. Only message and contract templates say yes.
     */
    protected static function includesSystemRows(): bool
    {
        return false;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

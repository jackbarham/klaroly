<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

/**
 * Wraps a _minor column so the model exposes a Money value object instead of
 * a bare integer.
 *
 * The currency comes from the model's own currency column when it has one.
 * Otherwise it comes from the model's booking, and failing that from its
 * account, because rate card prices and account-level defaults are always in
 * the account's currency.
 *
 * Writing accepts a Money or an int. Floats are refused outright, because a
 * float that has been through arithmetic cannot be trusted to be whole.
 *
 * @implements CastsAttributes<Money|null, Money|int|null>
 */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return new Money((int) $value, $this->currencyFor($model, $attributes));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof Money) {
            return [$key => $value->minor];
        }

        if (is_int($value)) {
            return [$key => $value];
        }

        if (is_float($value)) {
            throw new InvalidArgumentException($key.' must be a whole number of minor units, not a float.');
        }

        // A string of digits arrives from request input and from the database
        // driver on some platforms. Anything else is a programming error.
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return [$key => (int) $value];
        }

        throw new InvalidArgumentException($key.' must be a Money or an int.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function currencyFor(Model $model, array $attributes): string
    {
        if (! empty($attributes['currency'])) {
            return $attributes['currency'];
        }

        if (method_exists($model, 'booking') && $model->booking !== null) {
            return $model->booking->currency;
        }

        if (method_exists($model, 'account') && $model->account !== null) {
            return $model->account->currency;
        }

        throw new RuntimeException(sprintf(
            'Cannot work out the currency for %s on %s: no currency column, booking or account.',
            $model->getTable(),
            $model->getKey() ?? 'a new row',
        ));
    }
}

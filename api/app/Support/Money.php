<?php

namespace App\Support;

use InvalidArgumentException;
use NumberFormatter;

/**
 * An amount of money in a single currency, held as an integer number of the
 * currency's ISO 4217 minor units (pence, cents, and so on).
 *
 * Immutable: every operation returns a new instance. Two amounts in different
 * currencies never combine; comparing or adding them throws.
 */
final class Money
{
    /**
     * Currencies whose minor unit is not one hundredth of the major unit.
     * Everything not listed here has two decimal places.
     *
     * @var array<string, int>
     */
    private const MINOR_UNIT_DIGITS = [
        'BHD' => 3,
        'IQD' => 3,
        'JOD' => 3,
        'KWD' => 3,
        'LYD' => 3,
        'OMR' => 3,
        'TND' => 3,
        'CLP' => 0,
        'ISK' => 0,
        'JPY' => 0,
        'KRW' => 0,
        'PYG' => 0,
        'UGX' => 0,
        'VND' => 0,
        'XAF' => 0,
        'XOF' => 0,
        'XPF' => 0,
    ];

    public function __construct(
        public readonly int $minor,
        public readonly string $currency,
    ) {
        if (strlen($currency) !== 3 || strtoupper($currency) !== $currency) {
            throw new InvalidArgumentException('Currency must be an upper-case ISO 4217 code, got "'.$currency.'".');
        }
    }

    public static function of(int $minor, string $currency): self
    {
        return new self($minor, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    /**
     * A whole-number percentage of this amount, rounded half away from zero,
     * using integer arithmetic only.
     */
    public function percentage(int $percent): self
    {
        $product = $this->minor * $percent;

        if ($product >= 0) {
            $rounded = intdiv($product + 50, 100);
        } else {
            $rounded = -intdiv(-$product + 50, 100);
        }

        return new self($rounded, $this->currency);
    }

    /**
     * Negative when this is less than the other, zero when equal, positive
     * when greater. The same shape as the spaceship operator.
     */
    public function compare(Money $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minor <=> $other->minor;
    }

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function greaterThan(Money $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function lessThan(Money $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    /**
     * The smaller of this and the other amount.
     */
    public function min(Money $other): self
    {
        return $this->lessThan($other) ? $this : $other;
    }

    /**
     * The number of decimal places the currency uses for display.
     */
    public function minorUnitDigits(): int
    {
        return self::MINOR_UNIT_DIGITS[$this->currency] ?? 2;
    }

    /**
     * The amount as a plain decimal string with no symbol or grouping,
     * for example "1234.50" or "-8.00". Built from integer arithmetic.
     */
    public function toDecimalString(): string
    {
        $digits = $this->minorUnitDigits();
        $absolute = abs($this->minor);
        $sign = $this->minor < 0 ? '-' : '';

        if ($digits === 0) {
            return $sign.$absolute;
        }

        $scale = 10 ** $digits;
        $major = intdiv($absolute, $scale);
        $fraction = str_pad((string) ($absolute % $scale), $digits, '0', STR_PAD_LEFT);

        return $sign.$major.'.'.$fraction;
    }

    /**
     * The amount formatted for people in the given locale, with the symbol
     * and grouping that locale uses for this currency. The symbol comes from
     * ICU, never from a hard-coded lookup.
     *
     * NumberFormatter only accepts a float, so the decimal string is handed
     * over as one at the very last step. No arithmetic happens in floating
     * point and the value has already been rounded to the minor unit, so the
     * conversion cannot change the displayed digits.
     */
    public function format(string $locale): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $this->minorUnitDigits());
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $this->minorUnitDigits());

        $formatted = $formatter->formatCurrency((float) $this->toDecimalString(), $this->currency);

        if ($formatted === false) {
            throw new InvalidArgumentException('Could not format '.$this->currency.' for locale '.$locale.'.');
        }

        return $formatted;
    }

    public function __toString(): string
    {
        return $this->toDecimalString().' '.$this->currency;
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot combine '.$this->currency.' with '.$other->currency.'.');
        }
    }
}

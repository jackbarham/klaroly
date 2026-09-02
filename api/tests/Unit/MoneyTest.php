<?php

use App\Support\Money;

it('adds, subtracts and multiplies without leaving integers', function () {
    $a = new Money(1000, 'GBP');
    $b = new Money(250, 'GBP');

    expect($a->add($b)->minor)->toBe(1250)
        ->and($a->subtract($b)->minor)->toBe(750)
        ->and($b->multiply(3)->minor)->toBe(750)
        ->and($a->minor)->toBe(1000);
});

it('rounds percentages half away from zero', function () {
    expect((new Money(38000, 'GBP'))->percentage(25)->minor)->toBe(9500)
        ->and((new Money(1001, 'GBP'))->percentage(50)->minor)->toBe(501)
        ->and((new Money(-1001, 'GBP'))->percentage(50)->minor)->toBe(-501)
        ->and((new Money(333, 'GBP'))->percentage(10)->minor)->toBe(33);
});

it('compares and reports sign', function () {
    $small = new Money(1, 'GBP');
    $large = new Money(2, 'GBP');

    expect($small->compare($large))->toBe(-1)
        ->and($large->compare($small))->toBe(1)
        ->and($small->compare(new Money(1, 'GBP')))->toBe(0)
        ->and(Money::zero('GBP')->isZero())->toBeTrue()
        ->and((new Money(-5, 'GBP'))->isNegative())->toBeTrue()
        ->and($small->min($large)->minor)->toBe(1);
});

it('refuses to mix currencies', function () {
    expect(fn () => (new Money(1, 'GBP'))->add(new Money(1, 'EUR')))->toThrow(InvalidArgumentException::class);
});

it('writes a decimal string from integer parts', function () {
    expect((new Money(123456, 'GBP'))->toDecimalString())->toBe('1234.56')
        ->and((new Money(-5, 'GBP'))->toDecimalString())->toBe('-0.05')
        ->and((new Money(5000, 'JPY'))->toDecimalString())->toBe('5000')
        ->and((new Money(1234, 'KWD'))->toDecimalString())->toBe('1.234');
});

<?php

use App\Support\Decimal;

it('Decimal::amount rounds half up', function (string $kg, string $rate, string $rounding, string $expected) {
    expect(Decimal::amount($kg, $rate, $rounding))->toBe($expected);
})->with([
    'half a paisa rounds up' => ['0.005', '1', 'two_decimals', '0.01'],
    'decimals on both sides' => ['812.5', '52.5', 'two_decimals', '42656.25'],
    'whole rupee rounds half up' => ['100.5', '1', 'whole_rupee', '101.00'],
    'whole rupee rounds down below half' => ['100.4', '1', 'whole_rupee', '100.00'],
    'AT-1 purchase' => ['800', '50', 'two_decimals', '40000.00'],
    'four-decimal rate' => ['1.000', '52.3333', 'two_decimals', '52.33'],
]);

it('Decimal adds, subtracts and sums money exactly', function () {
    expect(Decimal::add('0.10', '0.20'))->toBe('0.30')
        ->and(Decimal::sub('100.00', '100.01'))->toBe('-0.01')
        ->and(Decimal::sum(['40000.00', '28800.00', '19600.00']))->toBe('88400.00')
        ->and(Decimal::sum([]))->toBe('0.00');
});

it('Decimal compares and tests the sign', function () {
    expect(Decimal::compare('10.00', '9.99'))->toBe(1)
        ->and(Decimal::compare('9.99', '10.00'))->toBe(-1)
        ->and(Decimal::compare('10.0', '10.00'))->toBe(0)
        ->and(Decimal::isPositive('0.01'))->toBeTrue()
        ->and(Decimal::isPositive('0.00'))->toBeFalse()
        ->and(Decimal::isPositive('-1.00'))->toBeFalse()
        ->and(Decimal::isZero('0.000'))->toBeTrue();
});

it('Decimal turns a value into money with exactly 2 decimals', function () {
    expect(Decimal::money('40000'))->toBe('40000.00')
        ->and(Decimal::money('52.5'))->toBe('52.50')
        ->and(Decimal::abs('-2000.00'))->toBe('2000.00')
        ->and(Decimal::negate('2000.00'))->toBe('-2000.00');
});

it('Decimal refuses an amount rounding it does not know', function () {
    Decimal::amount('1', '1', 'nearest_ten');
})->throws(InvalidArgumentException::class);

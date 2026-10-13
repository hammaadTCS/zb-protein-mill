<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use LogicException;

/**
 * The only helper for money, kg and rate maths (conventions §3, NFR-06).
 *
 * Values go in and come out as decimal strings; the arithmetic is exact
 * (Brick\Math\BigDecimal), never float. Rounding is always half up.
 * add, sub and sum keep the larger number of decimals of their inputs.
 */
final class Decimal
{
    /**
     * BR-PUR-03, BR-GEN-06: amount = kg × rate, rounded per `amount_rounding`
     * (`whole_rupee` → whole rupees; `two_decimals` → paisa). Always 2 decimals.
     *
     * @param  string|null  $rounding  null = the company setting
     */
    public static function amount(string $kg, string $rate, ?string $rounding = null): string
    {
        $rounding ??= self::roundingSetting();
        $product = BigDecimal::of($kg)->multipliedBy($rate);

        return match ($rounding) {
            'whole_rupee' => (string) $product->toScale(0, RoundingMode::HalfUp)->toScale(2),
            'two_decimals' => (string) $product->toScale(2, RoundingMode::HalfUp),
            default => throw new InvalidArgumentException("Unknown amount rounding {$rounding}."),
        };
    }

    /**
     * A value with exactly $scale decimals, half up.
     *
     * @param  int<0, max>  $scale
     */
    public static function toScale(string $value, int $scale): string
    {
        return (string) BigDecimal::of($value)->toScale($scale, RoundingMode::HalfUp);
    }

    /** A value as kg: exactly 3 decimals, half up. */
    public static function kg(string $value): string
    {
        return (string) BigDecimal::of($value === '' ? '0' : $value)->toScale(3, RoundingMode::HalfUp);
    }

    /** A value as money: exactly 2 decimals, half up. */
    public static function money(string $value): string
    {
        return (string) BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp);
    }

    public static function add(string $a, string $b): string
    {
        return (string) BigDecimal::of($a)->plus($b);
    }

    public static function sub(string $a, string $b): string
    {
        return (string) BigDecimal::of($a)->minus($b);
    }

    /**
     * @param  iterable<string>  $values
     * @param  string  $zero  the result for no values (sets the scale: '0.00' money, '0.000' kg)
     */
    public static function sum(iterable $values, string $zero = '0.00'): string
    {
        $total = BigDecimal::of($zero);

        foreach ($values as $value) {
            $total = $total->plus($value);
        }

        return (string) $total;
    }

    /**
     * a × b, rounded half up to the given number of decimals.
     *
     * @param  int<0, max>  $scale
     */
    public static function multiply(string $a, string $b, int $scale): string
    {
        return (string) BigDecimal::of($a)->multipliedBy($b)->toScale($scale, RoundingMode::HalfUp);
    }

    /**
     * a ÷ b, rounded half up to the given number of decimals.
     *
     * @param  int<0, max>  $scale
     */
    public static function divide(string $a, string $b, int $scale): string
    {
        return (string) BigDecimal::of($a)->dividedBy($b, $scale, RoundingMode::HalfUp);
    }

    /** -1, 0 or 1. */
    public static function compare(string $a, string $b): int
    {
        return BigDecimal::of($a)->compareTo($b);
    }

    public static function isPositive(string $value): bool
    {
        return BigDecimal::of($value)->isPositive();
    }

    public static function isNegative(string $value): bool
    {
        return BigDecimal::of($value)->isNegative();
    }

    public static function isZero(string $value): bool
    {
        return BigDecimal::of($value)->isZero();
    }

    public static function abs(string $value): string
    {
        return (string) BigDecimal::of($value)->abs();
    }

    public static function negate(string $value): string
    {
        return (string) BigDecimal::of($value)->negated();
    }

    private static function roundingSetting(): string
    {
        $rounding = Settings::get(SettingKeys::AMOUNT_ROUNDING);

        if (! is_string($rounding)) {
            throw new LogicException('The amount_rounding setting is empty (BR-GEN-07).');
        }

        return $rounding;
    }
}

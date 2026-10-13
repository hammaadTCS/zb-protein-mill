<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Settings\Settings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Numbers in server-made text (messages, PDFs), the same way as the screens
 * (BR-RPT-05, spec §15.2): lakh or international grouping, rounding per settings.
 * Works on strings only (conventions §3).
 */
final class NumberFormat
{
    /** "Rs 1,00,000.00" or "Rs 1,00,000" (amount_rounding). */
    public static function money(string $amount): string
    {
        $decimals = Settings::get('amount_rounding') === 'whole_rupee' ? 0 : 2;

        return 'Rs '.self::number($amount, $decimals);
    }

    /** "2,800 kg" with kg_decimals. */
    public static function kg(string $kg): string
    {
        return self::number($kg, self::decimals('kg_decimals', 3)).' kg';
    }

    /** "Rs 52.50" with rate_decimals. */
    public static function rate(string $rate): string
    {
        return 'Rs '.self::number($rate, self::decimals('rate_decimals', 2));
    }

    /** A kg or rate without trailing zeros, grouped: "800.000" → "800", "52.5000" → "52.5". */
    public static function plain(string $value): string
    {
        $number = BigDecimal::of($value)->strippedOfTrailingZeros();

        return self::number((string) $number, max(0, $number->getScale()));
    }

    /**
     * @param  int<0, max>  $decimals
     */
    public static function number(string $value, int $decimals): string
    {
        $rounded = BigDecimal::of($value)->toScale($decimals, RoundingMode::HalfUp);
        $parts = explode('.', (string) $rounded->abs(), 2);
        $text = self::group($parts[0], Settings::get('number_format') === 'international');

        if (isset($parts[1])) {
            $text .= '.'.$parts[1];
        }

        return $rounded->isNegative() ? '-'.$text : $text;
    }

    /**
     * A decimals setting; until setup is complete (BR-GEN-07) the plain default.
     *
     * @return int<0, max>
     */
    private static function decimals(string $key, int $default): int
    {
        $value = Settings::get($key);

        return max(0, is_int($value) ? $value : $default);
    }

    /** 1,00,000 (lakh) or 100,000 (international). */
    private static function group(string $whole, bool $international): string
    {
        if (strlen($whole) <= 3) {
            return $whole;
        }

        if ($international) {
            return (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);
        }

        $rest = (string) preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3));

        return $rest.','.substr($whole, -3);
    }
}

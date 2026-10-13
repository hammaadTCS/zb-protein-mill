<?php

namespace App\Rules;

use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A decimal number given as text, checked without floating point (conventions §3):
 * at most $scale decimals and between $min and $max.
 */
class DecimalString implements ValidationRule
{
    public function __construct(
        private readonly int $scale,
        private readonly string $min = '0',
        private readonly ?string $max = null,
        private readonly bool $minExclusive = false,
    ) {}

    /** A money amount: up to 14 digits and 2 decimals, at least 0 (or above 0). */
    public static function money(bool $positive = false): self
    {
        return new self(2, '0', '99999999999999.99', $positive);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $text = is_int($value) ? (string) $value : $value;

        if (! is_string($text) || preg_match('/^\d+(\.\d+)?$/', $text) !== 1) {
            $fail(__('Enter a number.'));

            return;
        }

        $number = BigDecimal::of($text);

        if ($number->getScale() > $this->scale) {
            $fail(__('Use at most :count decimals.', ['count' => $this->scale]));

            return;
        }

        $belowMin = $this->minExclusive ? $number->isLessThanOrEqualTo($this->min) : $number->isLessThan($this->min);

        if ($belowMin || ($this->max !== null && $number->isGreaterThan($this->max))) {
            $fail($this->minExclusive
                ? __('Enter a number greater than :min.', ['min' => $this->min])
                : __('Enter a number from :min to :max.', ['min' => $this->min, 'max' => $this->max ?? '']));
        }
    }
}

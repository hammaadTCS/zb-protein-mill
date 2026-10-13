<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Settings\Models\Setting;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Company settings (spec §9.6). Values are stored as text and returned typed:
 * integer -> int, boolean -> bool, decimal/date/choice/string -> string.
 * Decimals stay strings (conventions §3: never a float).
 */
final class Settings
{
    public const SETUP_INCOMPLETE = 'Setup is not complete. Fill in all required settings before entering transactions.';

    public static function get(string $key): string|int|bool|null
    {
        $definition = SettingKeys::definition($key);

        $raw = Setting::query()
            ->withoutGlobalScope('company')
            ->where('company_id', CurrentCompany::id())
            ->where('key', $key)
            ->value('value');

        return self::cast($definition['type'], $raw === null ? null : (string) $raw);
    }

    /**
     * Validates the value for its key and saves it for the user's company.
     *
     * @throws ValidationException when the value does not fit the key
     */
    public static function set(string $key, mixed $value, User $by): void
    {
        $stored = self::normalize($key, $value);

        $setting = Setting::query()
            ->withoutGlobalScope('company')
            ->firstOrNew(['company_id' => $by->company_id, 'key' => $key]);

        $setting->value_type = SettingKeys::definition($key)['type'];
        $setting->value = $stored;
        $setting->save();
    }

    /**
     * Required keys (BR-GEN-07) that still have no value.
     *
     * @return list<string>
     */
    public static function missingRequired(): array
    {
        $values = Setting::query()
            ->withoutGlobalScope('company')
            ->where('company_id', CurrentCompany::id())
            ->pluck('value', 'key')
            ->all();

        return array_values(array_filter(
            SettingKeys::required(),
            fn (string $key): bool => ($values[$key] ?? null) === null,
        ));
    }

    /**
     * BR-GEN-07: no transaction can be saved until every required setting is filled.
     *
     * @throws ValidationException with the glossary "Setup incomplete" message
     */
    public static function assertComplete(): void
    {
        if (self::missingRequired() !== []) {
            throw ValidationException::withMessages(['settings' => __(self::SETUP_INCOMPLETE)]);
        }
    }

    /**
     * Turns an input value into the text stored for that key.
     *
     * @throws ValidationException when the value does not fit the key
     */
    public static function normalize(string $key, mixed $value): string
    {
        try {
            $definition = SettingKeys::definition($key);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$key => __('Unknown setting.')]);
        }

        $label = $definition['label'];
        $text = is_bool($value) ? ($value ? '1' : '0') : (is_int($value) || is_string($value) ? trim((string) $value) : null);

        $stored = match ($definition['type']) {
            'string' => $text !== null && $text !== '' && mb_strlen($text) <= 150 ? $text : null,
            'decimal' => self::normalizeDecimal($text),
            'integer' => self::normalizeInteger($text, $definition['min'] ?? 0, $definition['max'] ?? null),
            'boolean' => match ($text) {
                '1', 'true' => '1',
                '0', 'false' => '0',
                default => null,
            },
            'date' => self::normalizeDate($text),
            'choice' => in_array($text, $definition['choices'] ?? [], true) ? $text : null,
            default => null,
        };

        if ($stored === null) {
            throw ValidationException::withMessages([$key => self::invalidMessage($label, $definition)]);
        }

        return $stored;
    }

    private static function cast(string $type, ?string $raw): string|int|bool|null
    {
        return match (true) {
            $raw === null => null,
            $type === 'integer' => (int) $raw,
            $type === 'boolean' => $raw === '1',
            default => $raw,
        };
    }

    /** A non-negative amount with at most 2 decimals, stored with exactly 2 (no float). */
    private static function normalizeDecimal(?string $text): ?string
    {
        if ($text === null || preg_match('/^(\d{1,14})(?:\.(\d{1,2}))?$/', $text, $m) !== 1) {
            return null;
        }

        $whole = ltrim($m[1], '0');

        return ($whole === '' ? '0' : $whole).'.'.str_pad($m[2] ?? '', 2, '0');
    }

    private static function normalizeInteger(?string $text, int $min, ?int $max): ?string
    {
        if ($text === null || preg_match('/^\d{1,6}$/', $text) !== 1) {
            return null;
        }

        $number = (int) $text;

        if ($number < $min || ($max !== null && $number > $max)) {
            return null;
        }

        return (string) $number;
    }

    private static function normalizeDate(?string $text): ?string
    {
        if ($text === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m) !== 1) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $text : null;
    }

    /**
     * @param  array{type: string, choices?: list<string>, min?: int, max?: int}  $definition
     */
    private static function invalidMessage(string $label, array $definition): string
    {
        $min = $definition['min'] ?? 0;

        return match ($definition['type']) {
            'decimal' => __(':label must be an amount of 0 or more with at most 2 decimals.', ['label' => $label]),
            'integer' => isset($definition['max'])
                ? __(':label must be a whole number from :min to :max.', ['label' => $label, 'min' => $min, 'max' => $definition['max']])
                : __(':label must be a whole number of at least :min.', ['label' => $label, 'min' => $min]),
            'boolean' => __(':label must be yes or no.', ['label' => $label]),
            'date' => __(':label must be a real date.', ['label' => $label]),
            'choice' => __(':label must be one of the listed choices.', ['label' => $label]),
            default => __(':label is required.', ['label' => $label]),
        };
    }
}

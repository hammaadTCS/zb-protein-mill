<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use InvalidArgumentException;

/**
 * Every settings key of spec §9.6 with its type, allowed values and whether it is
 * required (BR-GEN-07). OPEN values (spec §4) have no default.
 *
 * @phpstan-type Definition array{type: string, required: bool, label: string, choices?: list<string>, min?: int, max?: int}
 */
final class SettingKeys
{
    public const COMPANY_NAME = 'company_name';

    public const SYSTEM_START_DATE = 'system_start_date';

    public const PAYMENT_APPROVAL_LIMIT = 'payment_approval_limit';

    public const NEW_PARTY_FIRST_PAYMENT_LIMIT = 'new_party_first_payment_limit';

    public const EXPENSE_PARTY_REQUIRED_LIMIT = 'expense_party_required_limit';

    public const UNUSUAL_RATE_PERCENT = 'unusual_rate_percent';

    public const UNUSUAL_RATE_LOOKBACK = 'unusual_rate_lookback';

    public const RATE_PENDING_AGE_DAYS = 'rate_pending_age_days';

    public const ADVANCE_AGE_DAYS = 'advance_age_days';

    public const KG_DECIMALS = 'kg_decimals';

    public const RATE_DECIMALS = 'rate_decimals';

    public const AMOUNT_ROUNDING = 'amount_rounding';

    public const NUMBER_FORMAT = 'number_format';

    public const DATE_FORMAT = 'date_format';

    public const PLANT_HAS_SCALE = 'plant_has_scale';

    public const SLIP_NO_REQUIRED = 'slip_no_required';

    public const SLIP_PHOTO_REQUIRED = 'slip_photo_required';

    public const INSIGHTS_PERIOD_MONTHS = 'insights_period_months';

    public const OPENING_BALANCES_COMPLETED = 'opening_balances_completed';

    public const OWNER_TWO_FACTOR_REQUIRED = 'owner_two_factor_required';

    /** Defaults that exist in spec §9.6. Every other key starts empty (OPEN). */
    public const DEFAULTS = [
        self::INSIGHTS_PERIOD_MONTHS => '3',
        self::OPENING_BALANCES_COMPLETED => '0',
    ];

    /**
     * @return array<string, Definition>
     */
    public static function definitions(): array
    {
        return [
            self::COMPANY_NAME => ['type' => 'string', 'required' => true, 'label' => 'Company name'],
            self::SYSTEM_START_DATE => ['type' => 'date', 'required' => true, 'label' => 'System start date'],
            self::PAYMENT_APPROVAL_LIMIT => ['type' => 'decimal', 'required' => true, 'label' => 'Payment approval limit'],
            self::NEW_PARTY_FIRST_PAYMENT_LIMIT => ['type' => 'decimal', 'required' => true, 'label' => 'New party first payment limit'],
            self::EXPENSE_PARTY_REQUIRED_LIMIT => ['type' => 'decimal', 'required' => true, 'label' => 'Expense party required limit'],
            self::UNUSUAL_RATE_PERCENT => ['type' => 'decimal', 'required' => true, 'label' => 'Unusual rate percent'],
            self::UNUSUAL_RATE_LOOKBACK => ['type' => 'integer', 'required' => true, 'label' => 'Unusual rate lookback', 'min' => 1],
            self::RATE_PENDING_AGE_DAYS => ['type' => 'integer', 'required' => true, 'label' => 'Rate pending age days', 'min' => 0],
            self::ADVANCE_AGE_DAYS => ['type' => 'integer', 'required' => true, 'label' => 'Advance age days', 'min' => 0],
            self::KG_DECIMALS => ['type' => 'integer', 'required' => true, 'label' => 'Kg decimals', 'min' => 0, 'max' => 3],
            self::RATE_DECIMALS => ['type' => 'integer', 'required' => true, 'label' => 'Rate decimals', 'min' => 0, 'max' => 2],
            self::AMOUNT_ROUNDING => ['type' => 'choice', 'required' => true, 'label' => 'Amount rounding', 'choices' => ['whole_rupee', 'two_decimals']],
            self::NUMBER_FORMAT => ['type' => 'choice', 'required' => true, 'label' => 'Number format', 'choices' => ['lakh', 'international']],
            // O-18 shows two examples: 05-10-2026 and 05 Oct 2026.
            self::DATE_FORMAT => ['type' => 'choice', 'required' => true, 'label' => 'Date format', 'choices' => ['d-m-Y', 'd M Y']],
            self::PLANT_HAS_SCALE => ['type' => 'boolean', 'required' => true, 'label' => 'Plant has scale'],
            self::SLIP_NO_REQUIRED => ['type' => 'boolean', 'required' => true, 'label' => 'Slip number required'],
            self::SLIP_PHOTO_REQUIRED => ['type' => 'boolean', 'required' => true, 'label' => 'Slip photo required'],
            self::INSIGHTS_PERIOD_MONTHS => ['type' => 'integer', 'required' => false, 'label' => 'Insights period months', 'min' => 1],
            self::OPENING_BALANCES_COMPLETED => ['type' => 'boolean', 'required' => false, 'label' => 'Opening balances completed'],
            self::OWNER_TWO_FACTOR_REQUIRED => ['type' => 'boolean', 'required' => true, 'label' => 'Owner two-step login required'],
        ];
    }

    /**
     * @return Definition
     */
    public static function definition(string $key): array
    {
        return self::definitions()[$key] ?? throw new InvalidArgumentException("Unknown setting {$key}.");
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return list<string>
     */
    public static function required(): array
    {
        return array_keys(array_filter(self::definitions(), fn (array $d): bool => $d['required']));
    }
}

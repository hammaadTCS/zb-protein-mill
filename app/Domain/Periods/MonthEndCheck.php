<?php

declare(strict_types=1);

namespace App\Domain\Periods;

/**
 * One month-end check (BR-MEC-01 to BR-MEC-06): its key, whether it passes, and the
 * details shown on page 30. Bank and cash checks come once per account.
 */
final readonly class MonthEndCheck
{
    public const PREVIOUS_MONTHS = 'previous_months';

    public const PENDING_RATES = 'pending_rates';

    public const AWAITING_APPROVAL = 'awaiting_approval';

    public const TRIAL_BALANCE = 'trial_balance';

    public const BANK_MATCH = 'bank_match';

    public const CASH_MATCH = 'cash_match';

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $check,
        public bool $passed,
        public array $details = [],
    ) {}

    /**
     * @return array{check: string, passed: bool, details: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['check' => $this->check, 'passed' => $this->passed, 'details' => $this->details];
    }
}

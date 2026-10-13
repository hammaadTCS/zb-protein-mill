<?php

declare(strict_types=1);

namespace App\Domain\MoneyAccounts\Enums;

/** BR-ACC-01, BR-PAY-03: the payment method comes from the account type. */
enum MoneyAccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Enums;

/** BR-EXP-04 to BR-EXP-06: how an expense was paid. */
enum ExpensePaymentMode: string
{
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case PaidByEmployee = 'paid_by_employee';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Unpaid => 'Unpaid',
            self::PaidByEmployee => 'Paid by employee',
        };
    }
}

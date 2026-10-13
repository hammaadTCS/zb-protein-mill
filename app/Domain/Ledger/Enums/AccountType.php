<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

/** Spec §8.1 account types. */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';
}

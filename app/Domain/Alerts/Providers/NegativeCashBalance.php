<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;
use App\Support\Decimal;

/** BR-GEN-10: a cash account below zero means an entry is missing or wrong. */
final class NegativeCashBalance implements AlertProvider
{
    public function key(): string
    {
        return 'negative_cash_balance';
    }

    public function count(User $user): int
    {
        if (! $user->can('account.view')) {
            return 0;
        }

        return MoneyAccount::query()->active()->where('type', MoneyAccountType::Cash->value)->get()
            ->filter(fn (MoneyAccount $account): bool => Decimal::isNegative($account->balance()))
            ->count();
    }

    public function route(): string
    {
        return route('dashboard', [], false).'#balances';
    }

    public function label(): string
    {
        return __('Cash accounts below zero');
    }

    public function severity(): string
    {
        return 'danger';
    }
}

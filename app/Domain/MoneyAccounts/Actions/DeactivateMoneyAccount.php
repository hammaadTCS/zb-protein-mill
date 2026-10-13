<?php

declare(strict_types=1);

namespace App\Domain\MoneyAccounts\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;
use App\Support\Decimal;
use App\Support\NumberFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-ACC-02: an account can be made inactive only when its balance is zero.
 * It stays in history and reports (BR-GEN-08). Its ledger account stays active,
 * so entries made from it can still be edited or cancelled.
 */
final class DeactivateMoneyAccount
{
    public function handle(User $by, MoneyAccount $account): MoneyAccount
    {
        return DB::transaction(function () use ($by, $account): MoneyAccount {
            $account = MoneyAccount::query()->lockForUpdate()->findOrFail($account->id);

            if (! $account->is_active) {
                return $account;
            }

            $balance = $account->balance();

            if (! Decimal::isZero($balance)) {
                throw ValidationException::withMessages([
                    'account' => __('This account still has a balance of :amount.', ['amount' => NumberFormat::money($balance)]),
                ]);
            }

            $account->update(['is_active' => false, 'updated_by' => $by->id]);

            AuditLogger::log(AuditActions::UPDATED, $account, ['is_active' => true], ['is_active' => false], by: $by);

            return $account;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\MoneyAccounts\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Name and bank details only; the type never changes. Renames the ledger account too.
 */
final class UpdateMoneyAccount
{
    /**
     * @param  array{name: string, bank_name?: string|null, account_title?: string|null, account_number?: string|null}  $data
     */
    public function handle(User $by, MoneyAccount $account, array $data): MoneyAccount
    {
        return DB::transaction(function () use ($by, $account, $data): MoneyAccount {
            $account = MoneyAccount::query()->lockForUpdate()->findOrFail($account->id);
            $ledger = $account->ledgerAccount()->lockForUpdate()->firstOrFail();
            $old = CreateMoneyAccount::audited($account, $ledger);
            $isBank = $account->type === MoneyAccountType::Bank;

            $account->fill([
                'name' => $data['name'],
                'bank_name' => $isBank ? ($data['bank_name'] ?? null) : null,
                'account_title' => $isBank ? ($data['account_title'] ?? null) : null,
                'account_number' => $isBank ? ($data['account_number'] ?? null) : null,
                'updated_by' => $by->id,
            ]);

            if (! $account->isDirty()) {
                return $account;
            }

            $account->save();

            $ledger->update([
                'name' => MoneyAccount::ledgerName($account->type, $account->name),
                'updated_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::UPDATED, $account, $old, CreateMoneyAccount::audited($account, $ledger), by: $by);

            return $account;
        });
    }
}

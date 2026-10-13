<?php

declare(strict_types=1);

namespace App\Domain\MoneyAccounts\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\AccountCodeAllocator;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\NormalSide;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * BR-ACC-01: a new cash or bank account and its own ledger account, in one transaction.
 */
final class CreateMoneyAccount
{
    /**
     * @param  array{name: string, type: string, bank_name?: string|null, account_title?: string|null, account_number?: string|null}  $data
     */
    public function handle(User $by, array $data): MoneyAccount
    {
        return DB::transaction(function () use ($by, $data): MoneyAccount {
            $type = MoneyAccountType::from($data['type']);
            $isBank = $type === MoneyAccountType::Bank;

            $ledger = LedgerAccount::query()->create([
                'company_id' => $by->company_id,
                'code' => AccountCodeAllocator::next($type->value),
                'name' => MoneyAccount::ledgerName($type, $data['name']),
                'type' => AccountType::Asset,
                'normal_side' => NormalSide::Debit,
                'is_system' => false,
                'created_by' => $by->id,
            ]);

            $account = MoneyAccount::query()->create([
                'company_id' => $by->company_id,
                'name' => $data['name'],
                'type' => $type,
                'bank_name' => $isBank ? ($data['bank_name'] ?? null) : null,
                'account_title' => $isBank ? ($data['account_title'] ?? null) : null,
                'account_number' => $isBank ? ($data['account_number'] ?? null) : null,
                'ledger_account_id' => $ledger->id,
                'created_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CREATED, $account, [], self::audited($account, $ledger), by: $by);

            return $account;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function audited(MoneyAccount $account, LedgerAccount $ledger): array
    {
        return [
            'name' => $account->name,
            'type' => $account->type->value,
            'bank_name' => $account->bank_name,
            'account_title' => $account->account_title,
            'account_number' => $account->account_number,
            'ledger_account' => $ledger->code,
            'is_active' => $account->is_active,
        ];
    }
}

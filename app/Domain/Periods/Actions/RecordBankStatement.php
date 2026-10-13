<?php

declare(strict_types=1);

namespace App\Domain\Periods\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Periods\Models\BankReconciliation;
use App\Domain\Periods\Month;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-MEC-05: the bank statement's closing balance for a month, stored with the ledger
 * balance at the month's last day and the difference (statement minus ledger).
 */
final class RecordBankStatement
{
    public function handle(User $by, string $yyyymm, MoneyAccount $bank, string $statementBalance, ?string $note = null): BankReconciliation
    {
        $month = Month::of($yyyymm);

        if ($bank->type !== MoneyAccountType::Bank) {
            throw ValidationException::withMessages(['money_account_id' => __('Select a bank account.')]);
        }

        $text = trim($statementBalance);

        // A bank balance can be below zero (an overdraft).
        if (preg_match('/^-?\d+(\.\d{1,2})?$/', $text) !== 1) {
            throw ValidationException::withMessages(['statement_balance' => __('Enter the statement balance, at most 2 decimals.')]);
        }

        return DB::transaction(function () use ($by, $month, $bank, $text, $note): BankReconciliation {
            if ($month->isClosed()) {
                throw ValidationException::withMessages(['period' => __(':month is closed.', ['month' => $month->name()])]);
            }

            $statement = Decimal::money($text);
            $system = $bank->balance($month->lastDay());
            $period = $month->periodOrCreate();
            $note = $note === null || trim($note) === '' ? null : trim($note);

            $row = BankReconciliation::query()->create([
                'company_id' => $by->company_id,
                'period_id' => $period->id,
                'money_account_id' => $bank->id,
                'statement_balance' => $statement,
                'system_balance' => $system,
                'difference' => Decimal::sub($statement, $system),
                'note' => $note,
                'entered_by' => $by->id,
            ]);

            // Conventions §7 has no morph alias for bank statements: the audit row is on the period.
            AuditLogger::log(AuditActions::CREATED, $period, [], [
                'bank_statement' => $row->id,
                'money_account_id' => $bank->id,
                'statement_balance' => $row->statement_balance,
                'system_balance' => $row->system_balance,
                'difference' => $row->difference,
            ], by: $by);

            return $row;
        });
    }
}

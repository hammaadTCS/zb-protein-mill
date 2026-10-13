<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Ledger\AccountCodeAllocator;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\NormalSide;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** A new expense category and its ledger account (6000+, expense, debit, same name). */
final class CreateExpenseCategory
{
    /**
     * @param  array{name: string, is_quick?: bool, sort_order?: int}  $data
     */
    public function handle(User $by, array $data): ExpenseCategory
    {
        return DB::transaction(function () use ($by, $data): ExpenseCategory {
            $ledger = LedgerAccount::query()->create([
                'company_id' => $by->company_id,
                'code' => AccountCodeAllocator::next('expense_category'),
                'name' => $data['name'],
                'type' => AccountType::Expense,
                'normal_side' => NormalSide::Debit,
                'is_system' => false,
                'created_by' => $by->id,
            ]);

            $category = ExpenseCategory::query()->create([
                'company_id' => $by->company_id,
                'name' => $data['name'],
                'ledger_account_id' => $ledger->id,
                'is_quick' => (bool) ($data['is_quick'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'created_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CREATED, $category, [], [...self::audited($category), 'ledger_account' => $ledger->code], by: $by);

            return $category;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function audited(ExpenseCategory $category): array
    {
        return ['name' => $category->name, 'is_quick' => $category->is_quick, 'sort_order' => $category->sort_order, 'is_active' => $category->is_active];
    }
}

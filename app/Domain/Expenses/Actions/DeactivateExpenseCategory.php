<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * BR-GEN-08: an inactive category cannot be picked for new expenses; it stays in
 * history and reports. Its ledger account stays active for edits of old expenses.
 */
final class DeactivateExpenseCategory
{
    public function handle(User $by, ExpenseCategory $category): ExpenseCategory
    {
        return DB::transaction(function () use ($by, $category): ExpenseCategory {
            $category = ExpenseCategory::query()->lockForUpdate()->findOrFail($category->id);

            if (! $category->is_active) {
                return $category;
            }

            $category->update(['is_active' => false, 'updated_by' => $by->id]);
            AuditLogger::log(AuditActions::UPDATED, $category, ['is_active' => true], ['is_active' => false], by: $by);

            return $category;
        });
    }
}

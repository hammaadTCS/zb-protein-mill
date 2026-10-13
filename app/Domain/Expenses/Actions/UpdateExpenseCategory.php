<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Name, quick button and order; a new name renames the ledger account too. */
final class UpdateExpenseCategory
{
    /**
     * @param  array{name: string, is_quick?: bool, sort_order?: int}  $data
     */
    public function handle(User $by, ExpenseCategory $category, array $data): ExpenseCategory
    {
        return DB::transaction(function () use ($by, $category, $data): ExpenseCategory {
            $category = ExpenseCategory::query()->lockForUpdate()->findOrFail($category->id);
            $old = CreateExpenseCategory::audited($category);

            $category->fill([
                'name' => $data['name'],
                'is_quick' => (bool) ($data['is_quick'] ?? $category->is_quick),
                'sort_order' => (int) ($data['sort_order'] ?? $category->sort_order),
                'updated_by' => $by->id,
            ]);

            if (! $category->isDirty()) {
                return $category;
            }

            $renamed = $category->isDirty('name');
            $category->save();

            if ($renamed) {
                $category->ledgerAccount()->firstOrFail()->update(['name' => $category->name, 'updated_by' => $by->id]);
            }

            AuditLogger::log(AuditActions::UPDATED, $category, $old, CreateExpenseCategory::audited($category), by: $by);

            return $category;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Checks\CashGuard;
use App\Domain\Expenses\ExpenseInput;
use App\Domain\Expenses\Models\Expense;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingService;
use App\Domain\Periods\Periods;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\ConcurrentEdit;
use App\Support\EntryStatus;
use App\Support\Warnings\Warnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Edits an expense by reversal and repost (spec §6); refused in a closed month. */
final class UpdateExpense
{
    /**
     * @param  array<string, mixed>  $data  validated UpdateExpenseRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, Expense $expense, array $data, array $confirmedWarnings = []): Expense
    {
        return DB::transaction(function () use ($by, $expense, $data, $confirmedWarnings): Expense {
            Settings::assertComplete(); // BR-GEN-07
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            ConcurrentEdit::check($expense->updated_at, isset($data['version']) ? (string) $data['version'] : null); // BR-GEN-09

            if ($expense->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['date' => __('A cancelled entry cannot be edited.')]);
            }

            Periods::assertDateOpen($expense->entry_date);
            $oldPostingDate = $expense->journal_entry_id === null ? null : Journals::postingDate($expense->journal_entry_id);

            if ($oldPostingDate !== null) {
                Periods::assertDateOpen($oldPostingDate);
            }

            Periods::assertValidEntryDate((string) $data['date']); // BR-GEN-02
            $input = ExpenseInput::from($data);
            $warnings = new Warnings($confirmedWarnings);

            if ($input->account !== null) {
                CashGuard::check($warnings, $input->account, $input->amount, $expense); // BR-GEN-10
            }

            $old = CreateExpense::audited($expense);
            $expense->fill([...CreateExpense::fields($input), 'updated_by' => $by->id]);

            if (! $expense->isDirty()) {
                return $expense;
            }

            $expense->save();
            $expense->unsetRelation('category')->unsetRelation('moneyAccount')->unsetRelation('party');

            if ($oldPostingDate !== null && $expense->journal_entry_id !== null) {
                PostingService::reverseId($expense->journal_entry_id, $oldPostingDate, "Edit of {$expense->voucher_no}", $by); // PR-25
            }

            $journal = PostingService::post(CreateExpense::posting($expense), $by);
            $expense->update(['journal_entry_id' => $journal->id]);

            $new = CreateExpense::audited($expense);
            $changed = array_flip(array_keys(array_filter($new, fn ($value, $key) => ($old[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH)));
            AuditLogger::log(AuditActions::UPDATED, $expense, array_intersect_key($old, $changed), array_intersect_key($new, $changed), by: $by);
            $warnings->audit($expense, $by);

            return $expense;
        });
    }
}

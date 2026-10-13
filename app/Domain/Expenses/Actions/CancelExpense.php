<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Expenses\Models\Expense;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingService;
use App\Domain\Periods\Periods;
use App\Models\User;
use App\Support\CancelReason;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Cancels an expense: reversal, reason, the row stays (BR-GEN-03, BR-GEN-04). */
final class CancelExpense
{
    public function handle(User $by, Expense $expense, string $reason): Expense
    {
        return DB::transaction(function () use ($by, $expense, $reason): Expense {
            $reason = CancelReason::check($reason);
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            if ($expense->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['reason' => __('This entry is already cancelled.')]);
            }

            Periods::assertDateOpen($expense->entry_date);

            if ($expense->journal_entry_id !== null) {
                $postingDate = Journals::postingDate($expense->journal_entry_id);
                Periods::assertDateOpen($postingDate);
                PostingService::reverseId($expense->journal_entry_id, $postingDate, "Cancelled {$expense->voucher_no}", $by);
            }

            $expense->update([
                'status' => EntryStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
                'updated_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CANCELLED, $expense, ['status' => 'active'], ['status' => 'cancelled'], $reason, $by);

            return $expense;
        });
    }
}

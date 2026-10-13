<?php

declare(strict_types=1);

namespace App\Domain\Adjustments\Actions;

use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingService;
use App\Domain\Periods\Periods;
use App\Models\User;
use App\Support\CancelReason;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels an agreed adjustment: reversal, reason, the row stays (BR-GEN-03, BR-GEN-04).
 * A wrong adjustment is cancelled and entered again, never edited (BR-ADJ-06).
 */
final class CancelAdjustment
{
    public function handle(User $by, Adjustment $adjustment, string $reason): Adjustment
    {
        return DB::transaction(function () use ($by, $adjustment, $reason): Adjustment {
            $reason = CancelReason::check($reason);
            $adjustment = Adjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if ($adjustment->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['reason' => __('This entry is already cancelled.')]);
            }

            Periods::assertDateOpen($adjustment->entry_date);

            if ($adjustment->journal_entry_id !== null) {
                $postingDate = Journals::postingDate($adjustment->journal_entry_id);
                Periods::assertDateOpen($postingDate);
                PostingService::reverseId($adjustment->journal_entry_id, $postingDate, "Cancelled {$adjustment->voucher_no}", $by);
            }

            $adjustment->update([
                'status' => EntryStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
                'updated_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CANCELLED, $adjustment, ['status' => 'active'], ['status' => 'cancelled'], $reason, $by);

            return $adjustment;
        });
    }
}

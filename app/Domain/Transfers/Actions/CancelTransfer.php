<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingService;
use App\Domain\Periods\Periods;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;
use App\Support\CancelReason;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Cancels a transfer: reversal, reason, the row stays (BR-GEN-03, BR-GEN-04). */
final class CancelTransfer
{
    public function handle(User $by, Transfer $transfer, string $reason): Transfer
    {
        return DB::transaction(function () use ($by, $transfer, $reason): Transfer {
            $reason = CancelReason::check($reason);
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['reason' => __('This entry is already cancelled.')]);
            }

            Periods::assertDateOpen($transfer->entry_date);

            if ($transfer->journal_entry_id !== null) {
                $postingDate = Journals::postingDate($transfer->journal_entry_id);
                Periods::assertDateOpen($postingDate);
                PostingService::reverseId($transfer->journal_entry_id, $postingDate, "Cancelled {$transfer->voucher_no}", $by);
            }

            $transfer->update([
                'status' => EntryStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
                'updated_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CANCELLED, $transfer, ['status' => 'active'], ['status' => 'cancelled'], $reason, $by);

            return $transfer;
        });
    }
}

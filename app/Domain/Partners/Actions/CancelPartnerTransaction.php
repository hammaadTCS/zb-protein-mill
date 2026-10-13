<?php

declare(strict_types=1);

namespace App\Domain\Partners\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingService;
use App\Domain\Partners\Models\PartnerTransaction;
use App\Domain\Periods\Periods;
use App\Models\User;
use App\Support\CancelReason;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Cancels a partner entry: reversal, reason, the row stays (BR-GEN-03, BR-GEN-04). */
final class CancelPartnerTransaction
{
    public function handle(User $by, PartnerTransaction $entry, string $reason): PartnerTransaction
    {
        return DB::transaction(function () use ($by, $entry, $reason): PartnerTransaction {
            $reason = CancelReason::check($reason);
            $entry = PartnerTransaction::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['reason' => __('This entry is already cancelled.')]);
            }

            Periods::assertDateOpen($entry->entry_date);

            if ($entry->journal_entry_id !== null) {
                $postingDate = Journals::postingDate($entry->journal_entry_id);
                Periods::assertDateOpen($postingDate);
                PostingService::reverseId($entry->journal_entry_id, $postingDate, "Cancelled {$entry->voucher_no}", $by);
            }

            $entry->update([
                'status' => EntryStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
                'updated_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CANCELLED, $entry, ['status' => 'active'], ['status' => 'cancelled'], $reason, $by);

            return $entry;
        });
    }
}

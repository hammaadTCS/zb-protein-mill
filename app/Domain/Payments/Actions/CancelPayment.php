<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingService;
use App\Domain\Payments\Models\Payment;
use App\Domain\Periods\Periods;
use App\Models\User;
use App\Support\CancelReason;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a payment (BR-GEN-03, BR-GEN-04): its journal is reversed (PR-25) and the
 * row stays, with the reason, user and time. Refused in a closed month (BR-MEC-07).
 * Used by CancelPurchase ("Also cancel the linked payment", BR-PUR-12) and, from
 * T3.6, by the payment pages.
 */
final class CancelPayment
{
    public function handle(User $by, Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($by, $payment, $reason): Payment {
            $reason = CancelReason::check($reason);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['reason' => __('This entry is already cancelled.')]);
            }

            Periods::assertDateOpen($payment->entry_date);

            if ($payment->journal_entry_id !== null) {
                $postingDate = Journals::postingDate($payment->journal_entry_id);
                Periods::assertDateOpen($postingDate);
                PostingService::reverseId($payment->journal_entry_id, $postingDate, "Cancelled {$payment->voucher_no}", $by);
            }

            $payment->update([
                'status' => EntryStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
                'updated_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CANCELLED, $payment, ['status' => 'active'], ['status' => 'cancelled'], $reason, $by);

            return $payment;
        });
    }
}

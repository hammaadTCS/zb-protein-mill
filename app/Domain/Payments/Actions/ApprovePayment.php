<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\PostingService;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Periods\Periods;
use App\Models\User;
use App\Support\EntryStatus;
use App\Support\Today;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-PAY-08: the Owner approves a waiting payment. It is posted on its own date, or
 * on the approval date when that month is closed.
 */
final class ApprovePayment
{
    public function handle(User $owner, Payment $payment): Payment
    {
        return DB::transaction(function () use ($owner, $payment): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== EntryStatus::Active || $payment->approval_status !== ApprovalStatus::Pending) {
                throw ValidationException::withMessages(['payment' => __('Only a payment awaiting approval can be approved.')]);
            }

            $postingDate = Periods::isClosed($payment->entry_date) ? Today::date() : $payment->entry_date;
            $journal = PostingService::post(RecordPayment::posting($payment, $postingDate), $owner);

            $payment->update([
                'approval_status' => ApprovalStatus::Approved,
                'approved_by' => $owner->id,
                'approved_at' => now(),
                'journal_entry_id' => $journal->id,
                'updated_by' => $owner->id,
            ]);

            AuditLogger::log(AuditActions::APPROVED, $payment, ['approval_status' => 'pending'], ['approval_status' => 'approved', 'posting_date' => $postingDate], by: $owner);

            return $payment;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Support\CancelReason;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** BR-PAY-08: the Owner rejects a waiting payment with a reason. No journal; it stays visible. */
final class RejectPayment
{
    public function handle(User $owner, Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($owner, $payment, $reason): Payment {
            $reason = CancelReason::check($reason);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== EntryStatus::Active || $payment->approval_status !== ApprovalStatus::Pending) {
                throw ValidationException::withMessages(['payment' => __('Only a payment awaiting approval can be rejected.')]);
            }

            $payment->update([
                'approval_status' => ApprovalStatus::Rejected,
                'approved_by' => $owner->id,
                'approved_at' => now(),
                'rejection_reason' => $reason,
                'updated_by' => $owner->id,
            ]);

            AuditLogger::log(AuditActions::REJECTED, $payment, ['approval_status' => 'pending'], ['approval_status' => 'rejected'], $reason, $owner);

            return $payment;
        });
    }
}

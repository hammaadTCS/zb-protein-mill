<?php

declare(strict_types=1);

namespace App\Domain\Purchases\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingService;
use App\Domain\Payments\Actions\CancelPayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Models\Purchase;
use App\Models\User;
use App\Support\CancelReason;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a purchase (BR-GEN-03, BR-GEN-04): its journal is reversed and the row
 * stays with the reason, user and time. The "paid now" payment is cancelled only
 * when the user ticks "Also cancel the linked payment"; otherwise it stays and shows
 * as an advance (BR-PUR-12). Refused in a closed month (BR-PUR-14).
 */
final class CancelPurchase
{
    public function handle(User $by, Purchase $purchase, string $reason, bool $cancelLinkedPayment = false): Purchase
    {
        return DB::transaction(function () use ($by, $purchase, $reason, $cancelLinkedPayment): Purchase {
            $reason = CancelReason::check($reason); // BR-GEN-04
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['reason' => __('This entry is already cancelled.')]);
            }

            Periods::assertDateOpen($purchase->entry_date); // BR-PUR-14

            if ($purchase->journal_entry_id !== null) {
                $postingDate = Journals::postingDate($purchase->journal_entry_id);
                Periods::assertDateOpen($postingDate);
                PostingService::reverseId($purchase->journal_entry_id, $postingDate, "Cancelled {$purchase->voucher_no}", $by); // PR-25
            }

            $purchase->update([
                'status' => EntryStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
                'updated_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CANCELLED, $purchase, ['status' => 'active'], ['status' => 'cancelled'], $reason, $by);

            if ($cancelLinkedPayment) {
                Payment::query()->where('purchase_id', $purchase->id)->where('status', EntryStatus::Active->value)->get()
                    ->each(fn (Payment $payment) => app(CancelPayment::class)->handle($by, $payment, $reason));
            }

            return $purchase;
        });
    }
}

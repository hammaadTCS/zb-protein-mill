<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingService;
use App\Domain\Payments\Actions\CancelPayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Periods\Periods;
use App\Domain\Sales\Models\Sale;
use App\Models\User;
use App\Support\CancelReason;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a sale (BR-GEN-03, BR-GEN-04): its journal is reversed and the row stays
 * with the reason, user and time. The "received now" receipt is cancelled only when
 * the user ticks "Also cancel the linked payment"; otherwise it stays and shows as
 * an advance (BR-PUR-12 for sales). Refused in a closed month (BR-PUR-14).
 */
final class CancelSale
{
    public function handle(User $by, Sale $sale, string $reason, bool $cancelLinkedPayment = false): Sale
    {
        return DB::transaction(function () use ($by, $sale, $reason, $cancelLinkedPayment): Sale {
            $reason = CancelReason::check($reason); // BR-GEN-04
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($sale->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['reason' => __('This entry is already cancelled.')]);
            }

            Periods::assertDateOpen($sale->entry_date); // BR-PUR-14

            if ($sale->journal_entry_id !== null) {
                $postingDate = Journals::postingDate($sale->journal_entry_id);
                Periods::assertDateOpen($postingDate);
                PostingService::reverseId($sale->journal_entry_id, $postingDate, "Cancelled {$sale->voucher_no}", $by); // PR-25
            }

            $sale->update([
                'status' => EntryStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
                'updated_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CANCELLED, $sale, ['status' => 'active'], ['status' => 'cancelled'], $reason, $by);

            if ($cancelLinkedPayment) {
                Payment::query()->where('sale_id', $sale->id)->where('status', EntryStatus::Active->value)->get()
                    ->each(fn (Payment $payment) => app(CancelPayment::class)->handle($by, $payment, $reason));
            }

            return $sale;
        });
    }
}

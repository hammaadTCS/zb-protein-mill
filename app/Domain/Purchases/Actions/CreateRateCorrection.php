<?php

declare(strict_types=1);

namespace App\Domain\Purchases\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateChange;
use App\Domain\Purchases\Models\RateCorrection;
use App\Domain\Purchases\RateCorrections;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\CancelReason;
use App\Support\Decimal;
use App\Support\EntryChecks;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-LED-09, BR-PUR-14: a rate change for a purchase or sale in a closed month. Only
 * the difference between the new amount and the current effective amount (original
 * plus earlier corrections) is posted, in an open month (PR-26, PR-27). The original
 * row is never edited.
 */
final class CreateRateCorrection
{
    public function handle(User $by, Purchase|Sale $entry, string $newRate, string $date, string $reason): RateCorrection
    {
        return DB::transaction(function () use ($by, $entry, $newRate, $date, $reason): RateCorrection {
            Settings::assertComplete(); // BR-GEN-07
            Periods::assertValidEntryDate($date); // BR-GEN-02: an open month, not in the future

            $entry = $entry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->status !== EntryStatus::Active || $entry->rate_status !== RateStatus::Fixed || $entry->amount === null) {
                throw ValidationException::withMessages(['rate' => __('Only an active entry with a fixed rate can be corrected.')]);
            }

            if (! Periods::isClosed($entry->entry_date)) {
                throw ValidationException::withMessages(['rate' => __('This entry is in an open month. Edit it instead.')]);
            }

            if ($date < $entry->entry_date) {
                throw ValidationException::withMessages(['date' => __('The date cannot be before the entry date.')]);
            }

            $reason = CancelReason::check($reason); // a reason of at least 5 characters, as for cancelling

            $rate = EntryChecks::rate($newRate);
            $current = RateCorrections::effective($entry);
            $oldRate = (string) $current['rate'];
            $oldAmount = (string) $current['amount'];
            $newAmount = Decimal::amount((string) $entry->kg, $rate); // BR-GEN-06
            $difference = Decimal::sub($newAmount, $oldAmount);

            if (Decimal::isZero($difference)) {
                throw ValidationException::withMessages(['rate' => __('The new rate gives the same amount.')]);
            }

            $correction = RateCorrection::query()->create([
                'company_id' => $by->company_id,
                'voucher_no' => VoucherNumberService::next('rate_correction'), // BR-GEN-05
                'correction_date' => $date,
                'target_type' => $entry instanceof Purchase ? 'purchase' : 'sale',
                'purchase_id' => $entry instanceof Purchase ? $entry->id : null,
                'sale_id' => $entry instanceof Sale ? $entry->id : null,
                'old_rate' => $oldRate,
                'new_rate' => $rate,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'difference' => $difference,
                'reason' => $reason,
                'created_by' => $by->id,
            ]);

            $posting = $entry instanceof Purchase
                ? PostingRules::pr26RateCorrectionPurchase($correction, $entry)
                : PostingRules::pr27RateCorrectionSale($correction, $entry);
            $journal = PostingService::post($posting, $by);
            $correction->update(['journal_entry_id' => $journal->id]);

            RateChange::record($entry, $oldRate, $rate, $oldAmount, $newAmount, $by, $reason); // BR-PUR-09
            AuditLogger::log(AuditActions::CREATED, $correction, [], [
                'voucher_no' => $correction->voucher_no,
                'correction_date' => $correction->correction_date,
                'target' => $entry->voucher_no,
                'old_rate' => $correction->old_rate,
                'new_rate' => $correction->new_rate,
                'old_amount' => $correction->old_amount,
                'new_amount' => $correction->new_amount,
                'difference' => $correction->difference,
            ], $reason, $by);

            return $correction;
        });
    }
}

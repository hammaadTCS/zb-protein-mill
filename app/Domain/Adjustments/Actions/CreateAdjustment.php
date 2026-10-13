<?php

declare(strict_types=1);

namespace App\Domain\Adjustments\Actions;

use App\Domain\Adjustments\Adjustments;
use App\Domain\Adjustments\Enums\AdjustmentReason;
use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\PostingRequest;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Decimal;
use App\Support\EntryChecks;
use App\Support\EntryStatus;
use App\Support\Idempotency;
use App\Support\NumberFormat;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spec §11.7: an amount both sides agreed to deduct from a purchase (PR-08) or a sale
 * (PR-07). The original entry is never changed (D-13); adjustments are never edited
 * (BR-ADJ-06).
 */
final class CreateAdjustment
{
    /**
     * @param  array<string, mixed>  $data  validated StoreAdjustmentRequest fields
     */
    public function handle(User $by, Purchase|Sale $target, array $data): Adjustment
    {
        $key = (string) $data['idempotency_key'];

        try {
            return DB::transaction(function () use ($by, $target, $data, $key): Adjustment {
                Settings::assertComplete(); // BR-GEN-07
                $date = (string) $data['date'];
                Periods::assertValidEntryDate($date); // BR-GEN-02, BR-ADJ-05: an open month

                $existing = Idempotency::existing(Adjustment::class, $by, $key); // BR-GEN-11

                if ($existing !== null) {
                    return $existing;
                }

                // Locked so two adjustments at once cannot pass the maximum together.
                $target = $target::query()->lockForUpdate()->findOrFail($target->id);

                if ($target->status !== EntryStatus::Active || $target->rate_status !== RateStatus::Fixed || $target->amount === null) {
                    throw ValidationException::withMessages(['amount' => __('Only an active entry with a fixed rate can be adjusted.')]); // BR-ADJ-01
                }

                if ($date < $target->entry_date) {
                    throw ValidationException::withMessages(['date' => __('The date cannot be before the entry date.')]); // BR-ADJ-05
                }

                $amount = EntryChecks::amount((string) ($data['amount'] ?? '')); // BR-ADJ-03
                $maximum = Decimal::sub(Adjustments::base($target), Adjustments::activeTotal($target));

                if (Decimal::compare($amount, $maximum) > 0) {
                    throw ValidationException::withMessages(['amount' => __('The most that can be adjusted is :max.', ['max' => NumberFormat::money($maximum)])]); // BR-ADJ-03
                }

                $reason = AdjustmentReason::tryFrom((string) ($data['reason'] ?? ''))
                    ?? throw ValidationException::withMessages(['reason' => __('Choose quality, weight, freight or other.')]); // BR-ADJ-04
                $note = trim((string) ($data['note'] ?? ''));

                if ($note === '') {
                    throw ValidationException::withMessages(['note' => __('Write what was agreed.')]); // BR-ADJ-04
                }

                $adjustment = Adjustment::query()->create([
                    'company_id' => $by->company_id,
                    'voucher_no' => VoucherNumberService::next('adjustment'), // BR-GEN-05
                    'entry_date' => $date,
                    'target_type' => $target instanceof Purchase ? 'purchase' : 'sale',
                    'purchase_id' => $target instanceof Purchase ? $target->id : null,
                    'sale_id' => $target instanceof Sale ? $target->id : null,
                    'party_id' => $target->party_id, // BR-ADJ-02
                    'amount' => $amount,
                    'reason' => $reason,
                    'note' => $note,
                    'idempotency_key' => $key,
                    'created_by' => $by->id,
                ]);

                $journal = PostingService::post(self::posting($adjustment), $by);
                $adjustment->update(['journal_entry_id' => $journal->id]);

                AuditLogger::log(AuditActions::CREATED, $adjustment, [], [
                    'voucher_no' => $adjustment->voucher_no,
                    'entry_date' => $adjustment->entry_date,
                    'target' => $target->voucher_no,
                    'party_id' => $adjustment->party_id,
                    'amount' => $adjustment->amount,
                    'reason' => $adjustment->reason->value,
                    'note' => $adjustment->note,
                ], by: $by);

                return $adjustment;
            });
        } catch (QueryException $e) {
            if (Idempotency::isDuplicateKeyViolation($e)) {
                return Idempotency::existing(Adjustment::class, $by, $key) ?? throw $e;
            }

            throw $e;
        }
    }

    /** PR-07 on a sale, PR-08 on a purchase. */
    public static function posting(Adjustment $adjustment, ?string $postingDate = null): PostingRequest
    {
        return $adjustment->target_type === 'sale'
            ? PostingRules::pr07SaleAdjustment($adjustment, $postingDate)
            : PostingRules::pr08PurchaseAdjustment($adjustment, $postingDate);
    }
}

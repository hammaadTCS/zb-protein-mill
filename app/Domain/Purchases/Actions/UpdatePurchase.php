<?php

declare(strict_types=1);

namespace App\Domain\Purchases\Actions;

use App\Domain\Adjustments\Adjustments;
use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateChange;
use App\Domain\Purchases\PurchaseInput;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\ConcurrentEdit;
use App\Support\Decimal;
use App\Support\EntryStatus;
use App\Support\EntryWarnings;
use App\Support\Warnings\Warnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-PUR-11: every field except the voucher number may change. A posted purchase is
 * reversed on its original posting date and posted again on its (new) entry date;
 * `journal_entry_id` points to the new journal. A rate-pending purchase changes
 * without any journal. Refused in a closed month (BR-PUR-14) and when another user
 * saved first (BR-GEN-09).
 */
final class UpdatePurchase
{
    /**
     * @param  array<string, mixed>  $data  validated UpdatePurchaseRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, Purchase $purchase, array $data, array $confirmedWarnings = []): Purchase
    {
        return DB::transaction(function () use ($by, $purchase, $data, $confirmedWarnings): Purchase {
            $warnings = new Warnings($confirmedWarnings);
            Settings::assertComplete(); // BR-GEN-07
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            ConcurrentEdit::check($purchase->updated_at, isset($data['version']) ? (string) $data['version'] : null); // BR-GEN-09

            if ($purchase->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['date' => __('A cancelled entry cannot be edited.')]);
            }

            // BR-PUR-14: the old date, the old posting date and the new date must all be open.
            Periods::assertDateOpen($purchase->entry_date);
            $oldPostingDate = $purchase->journal_entry_id === null ? null : Journals::postingDate($purchase->journal_entry_id);

            if ($oldPostingDate !== null) {
                Periods::assertDateOpen($oldPostingDate);
            }

            Periods::assertValidEntryDate((string) $data['date']); // BR-GEN-02
            $input = PurchaseInput::from($data);

            if ($input->rate === null && $purchase->rate_status === RateStatus::Fixed) {
                throw ValidationException::withMessages(['rate' => __('A fixed rate cannot be removed. Enter the correct rate.')]);
            }

            EntryWarnings::slipRules($input->slipNo, true, $input->ourWeightKg, checkPhoto: false); // BR-PUR-07

            if ($input->rate !== null && ($input->rate !== $purchase->rate || $input->item->id !== $purchase->item_id)) {
                EntryWarnings::unusualRate($warnings, Purchase::class, $input->item->id, $input->rate, $purchase->id); // BR-PUR-06
            }

            EntryWarnings::duplicate($warnings, Purchase::class, 'purchase', $input->party->id, $input->item->id, $input->kg, $input->date, $purchase->id); // BR-PUR-08

            $old = CreatePurchase::audited($purchase);
            $oldRate = $purchase->rate;
            $oldAmount = $purchase->amount;

            $purchase->fill([
                'entry_date' => $input->date,
                'party_id' => $input->party->id,
                'item_id' => $input->item->id,
                'kg' => $input->kg,
                'rate' => $input->rate,
                'amount' => $input->rate === null ? null : Decimal::amount($input->kg, $input->rate), // BR-GEN-06
                'rate_status' => $input->rate === null ? RateStatus::Pending : RateStatus::Fixed,
                'rate_fixed_at' => $purchase->rate_fixed_at ?? ($input->rate === null ? null : now()),
                'slip_no' => $input->slipNo,
                'our_weight_kg' => $input->ourWeightKg,
                'note' => $input->note,
                'updated_by' => $by->id,
            ]);

            // BR-ADJ-02, BR-ADJ-03: active agreed adjustments must still fit the edited entry.
            if ($purchase->isDirty(['party_id', 'amount']) && Adjustments::hasActive($purchase)
                && ($purchase->isDirty('party_id') || Decimal::compare((string) $purchase->amount, Adjustments::activeTotal($purchase)) < 0)) {
                throw ValidationException::withMessages(['kg' => __('Cancel the agreed adjustments first.')]);
            }

            if (! $purchase->isDirty()) {
                return $purchase;
            }

            $rateChanged = $purchase->isDirty('rate');
            $purchase->save();
            $purchase->setRelation('item', $input->item);

            if ($oldPostingDate !== null && $purchase->journal_entry_id !== null) {
                PostingService::reverseId($purchase->journal_entry_id, $oldPostingDate, "Edit of {$purchase->voucher_no}", $by); // PR-25
            }

            if ($purchase->rate_status === RateStatus::Fixed) {
                $journal = PostingService::post(PostingRules::pr01PurchaseFixedRate($purchase), $by); // PR-01
                $purchase->update(['journal_entry_id' => $journal->id]);
            }

            if ($rateChanged && $purchase->rate !== null && $purchase->amount !== null) {
                RateChange::record($purchase, $oldRate, $purchase->rate, $oldAmount, $purchase->amount, $by); // BR-PUR-09
                AuditLogger::log($oldRate === null ? AuditActions::RATE_ADDED : AuditActions::RATE_CHANGED, $purchase, ['rate' => $oldRate, 'amount' => $oldAmount], ['rate' => $purchase->rate, 'amount' => $purchase->amount], by: $by);
            }

            $new = CreatePurchase::audited($purchase);
            $changed = array_keys(array_filter($new, fn ($value, $key) => ($old[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));
            AuditLogger::log(AuditActions::UPDATED, $purchase, array_intersect_key($old, array_flip($changed)), array_intersect_key($new, array_flip($changed)), by: $by);
            $warnings->audit($purchase, $by);

            return $purchase;
        });
    }
}

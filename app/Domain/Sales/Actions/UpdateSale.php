<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Adjustments\Adjustments;
use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\RateChange;
use App\Domain\Sales\CreditLimit;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\SaleInput;
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
 * BR-PUR-11 for sales: every field except the voucher number may change. A posted sale is
 * reversed on its original posting date and posted again on its (new) entry date;
 * `journal_entry_id` points to the new journal. A rate-pending sale changes
 * without any journal. Refused in a closed month (BR-PUR-14) and when another user
 * saved first (BR-GEN-09).
 */
final class UpdateSale
{
    /**
     * @param  array<string, mixed>  $data  validated UpdateSaleRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, Sale $sale, array $data, array $confirmedWarnings = []): Sale
    {
        return DB::transaction(function () use ($by, $sale, $data, $confirmedWarnings): Sale {
            $warnings = new Warnings($confirmedWarnings);
            Settings::assertComplete(); // BR-GEN-07
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            ConcurrentEdit::check($sale->updated_at, isset($data['version']) ? (string) $data['version'] : null); // BR-GEN-09

            if ($sale->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['date' => __('A cancelled entry cannot be edited.')]);
            }

            // BR-PUR-14: the old date, the old posting date and the new date must all be open.
            Periods::assertDateOpen($sale->entry_date);
            $oldPostingDate = $sale->journal_entry_id === null ? null : Journals::postingDate($sale->journal_entry_id);

            if ($oldPostingDate !== null) {
                Periods::assertDateOpen($oldPostingDate);
            }

            Periods::assertValidEntryDate((string) $data['date']); // BR-GEN-02
            $input = SaleInput::from($data);

            if ($input->rate === null && $sale->rate_status === RateStatus::Fixed) {
                throw ValidationException::withMessages(['rate' => __('A fixed rate cannot be removed. Enter the correct rate.')]);
            }

            EntryWarnings::slipRules($input->slipNo, true, $input->ourWeightKg, checkPhoto: false); // BR-PUR-07

            if ($input->rate !== null && ($input->rate !== $sale->rate || $input->item->id !== $sale->item_id)) {
                EntryWarnings::unusualRate($warnings, Sale::class, $input->item->id, $input->rate, $sale->id); // BR-PUR-06
            }

            EntryWarnings::duplicate($warnings, Sale::class, 'sale', $input->party->id, $input->item->id, $input->kg, $input->date, $sale->id); // BR-PUR-08

            if ($input->rate !== null) {
                $newAmount = Decimal::amount($input->kg, $input->rate);
                $previous = $sale->party_id === $input->party->id ? ($sale->amount ?? '0.00') : '0.00';
                CreditLimit::check($warnings, $input->party, Decimal::sub($newAmount, $previous)); // BR-SAL-06
            }

            $old = CreateSale::audited($sale);
            $oldRate = $sale->rate;
            $oldAmount = $sale->amount;

            $sale->fill([
                'entry_date' => $input->date,
                'party_id' => $input->party->id,
                'item_id' => $input->item->id,
                'kg' => $input->kg,
                'rate' => $input->rate,
                'amount' => $input->rate === null ? null : Decimal::amount($input->kg, $input->rate), // BR-GEN-06
                'rate_status' => $input->rate === null ? RateStatus::Pending : RateStatus::Fixed,
                'rate_fixed_at' => $sale->rate_fixed_at ?? ($input->rate === null ? null : now()),
                'slip_no' => $input->slipNo,
                'our_weight_kg' => $input->ourWeightKg,
                'note' => $input->note,
                'updated_by' => $by->id,
            ]);

            // BR-ADJ-02, BR-ADJ-03: active agreed adjustments must still fit the edited entry.
            if ($sale->isDirty(['party_id', 'amount']) && Adjustments::hasActive($sale)
                && ($sale->isDirty('party_id') || Decimal::compare((string) $sale->amount, Adjustments::activeTotal($sale)) < 0)) {
                throw ValidationException::withMessages(['kg' => __('Cancel the agreed adjustments first.')]);
            }

            if (! $sale->isDirty()) {
                return $sale;
            }

            $rateChanged = $sale->isDirty('rate');
            $sale->save();
            $sale->setRelation('item', $input->item);

            if ($oldPostingDate !== null && $sale->journal_entry_id !== null) {
                PostingService::reverseId($sale->journal_entry_id, $oldPostingDate, "Edit of {$sale->voucher_no}", $by); // PR-25
            }

            if ($sale->rate_status === RateStatus::Fixed) {
                $journal = PostingService::post(PostingRules::pr04SaleFixedRate($sale), $by); // PR-01
                $sale->update(['journal_entry_id' => $journal->id]);
            }

            if ($rateChanged && $sale->rate !== null && $sale->amount !== null) {
                RateChange::record($sale, $oldRate, $sale->rate, $oldAmount, $sale->amount, $by); // BR-PUR-09
                AuditLogger::log($oldRate === null ? AuditActions::RATE_ADDED : AuditActions::RATE_CHANGED, $sale, ['rate' => $oldRate, 'amount' => $oldAmount], ['rate' => $sale->rate, 'amount' => $sale->amount], by: $by);
            }

            $new = CreateSale::audited($sale);
            $changed = array_keys(array_filter($new, fn ($value, $key) => ($old[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));
            AuditLogger::log(AuditActions::UPDATED, $sale, array_intersect_key($old, array_flip($changed)), array_intersect_key($new, array_flip($changed)), by: $by);
            $warnings->audit($sale, $by);

            return $sale;
        });
    }
}

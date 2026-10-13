<?php

declare(strict_types=1);

namespace App\Domain\Purchases\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateChange;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Decimal;
use App\Support\EntryChecks;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds the rate to a rate-pending purchase (BR-PUR-04): amount = kg × rate, PR-01 is
 * posted, a rate_changes row is written (BR-PUR-09). The journal is dated on the
 * purchase date when that month is open, otherwise on the day the rate is added
 * (BR-LED-08); the purchase keeps its own date.
 */
final class AddPurchaseRate
{
    /**
     * @param  string  $date  Y-m-d, the day the rate is added
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, Purchase $purchase, string $rate, string $date, array $confirmedWarnings = []): Purchase
    {
        return DB::transaction(function () use ($by, $purchase, $rate, $date): Purchase {
            Settings::assertComplete(); // BR-GEN-07
            $purchase = Purchase::query()->with('item')->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== EntryStatus::Active || $purchase->rate_status !== RateStatus::Pending) {
                throw ValidationException::withMessages(['rate' => __('Only an active rate-pending entry can get a rate.')]);
            }

            $rate = EntryChecks::rate($rate);
            $postingDate = Periods::isClosed($purchase->entry_date) ? $date : $purchase->entry_date; // BR-LED-08
            Periods::assertValidEntryDate($postingDate);

            $purchase->fill([
                'rate' => $rate,
                'amount' => Decimal::amount($purchase->kg, $rate), // BR-GEN-06
                'rate_status' => RateStatus::Fixed,
                'rate_fixed_at' => now(),
                'updated_by' => $by->id,
            ]);
            $purchase->save();

            $journal = PostingService::post(PostingRules::pr01PurchaseFixedRate($purchase, $postingDate), $by);
            $purchase->update(['journal_entry_id' => $journal->id]);

            RateChange::record($purchase, null, (string) $purchase->rate, null, (string) $purchase->amount, $by);
            AuditLogger::log(AuditActions::RATE_ADDED, $purchase, ['rate' => null, 'amount' => null], ['rate' => $purchase->rate, 'amount' => $purchase->amount, 'posting_date' => $postingDate], by: $by);

            return $purchase;
        });
    }
}

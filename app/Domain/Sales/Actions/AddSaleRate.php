<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\RateChange;
use App\Domain\Sales\CreditLimit;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Decimal;
use App\Support\EntryChecks;
use App\Support\EntryStatus;
use App\Support\EntryWarnings;
use App\Support\Warnings\Warnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds the rate to a rate-pending sale (BR-PUR-04 for sales, spec §10.5): amount =
 * kg × rate, PR-04 is posted, a rate_changes row is written (BR-PUR-09). The journal
 * is dated on the sale date when that month is open, otherwise on the day the rate is
 * added (BR-LED-08); the sale keeps its own date.
 */
final class AddSaleRate
{
    /**
     * @param  string  $date  Y-m-d, the day the rate is added
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, Sale $sale, string $rate, string $date, array $confirmedWarnings = []): Sale
    {
        return DB::transaction(function () use ($by, $sale, $rate, $date, $confirmedWarnings): Sale {
            $warnings = new Warnings($confirmedWarnings);
            Settings::assertComplete(); // BR-GEN-07
            $sale = Sale::query()->with(['item', 'party'])->lockForUpdate()->findOrFail($sale->id);

            if ($sale->status !== EntryStatus::Active || $sale->rate_status !== RateStatus::Pending) {
                throw ValidationException::withMessages(['rate' => __('Only an active rate-pending entry can get a rate.')]);
            }

            $rate = EntryChecks::rate($rate);
            EntryWarnings::unusualRate($warnings, Sale::class, $sale->item_id, $rate, $sale->id); // BR-PUR-06
            CreditLimit::check($warnings, $sale->party, Decimal::amount($sale->kg, $rate)); // BR-SAL-06
            $postingDate = Periods::isClosed($sale->entry_date) ? $date : $sale->entry_date; // BR-LED-08
            Periods::assertValidEntryDate($postingDate);

            $sale->fill([
                'rate' => $rate,
                'amount' => Decimal::amount($sale->kg, $rate), // BR-GEN-06
                'rate_status' => RateStatus::Fixed,
                'rate_fixed_at' => now(),
                'updated_by' => $by->id,
            ]);
            $sale->save();

            $journal = PostingService::post(PostingRules::pr04SaleFixedRate($sale, $postingDate), $by);
            $sale->update(['journal_entry_id' => $journal->id]);

            RateChange::record($sale, null, (string) $sale->rate, null, (string) $sale->amount, $by);
            AuditLogger::log(AuditActions::RATE_ADDED, $sale, ['rate' => null, 'amount' => null], ['rate' => $sale->rate, 'amount' => $sale->amount, 'posting_date' => $postingDate], by: $by);
            $warnings->audit($sale, $by);

            return $sale;
        });
    }
}

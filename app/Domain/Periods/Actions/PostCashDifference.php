<?php

declare(strict_types=1);

namespace App\Domain\Periods\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Checks\CashCounts;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Periods\Month;
use App\Domain\Periods\Periods;
use App\Models\User;
use App\Support\Dates;
use App\Support\Decimal;
use App\Support\NumberFormat;
use App\Support\Today;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-MEC-06: the Owner posts the cash difference left at month end as a shortage
 * (PR-23) or an excess (PR-24), dated the month's last day, voucher CSH, with a note.
 * The amount must be exactly the difference between the latest count on the last day
 * and the ledger, so a second tap or a typing slip cannot post twice.
 */
final class PostCashDifference
{
    public const SHORTAGE = 'shortage';

    public const EXCESS = 'excess';

    /** @return string the CSH voucher number */
    public function handle(User $owner, string $yyyymm, MoneyAccount $cash, string $amount, string $direction, string $note): string
    {
        $month = Month::of($yyyymm);
        CashCounts::assertCash($cash);
        $text = trim($amount);
        $note = trim($note);

        if (! in_array($direction, [self::SHORTAGE, self::EXCESS], true)) {
            throw ValidationException::withMessages(['direction' => __('Select shortage or excess.')]);
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $text) !== 1 || ! Decimal::isPositive($text)) {
            throw ValidationException::withMessages(['amount' => __('Enter an amount greater than 0, at most 2 decimals.')]);
        }

        if ($note === '') {
            throw ValidationException::withMessages(['note' => __('Enter a note.')]);
        }

        if ($month->lastDay() > Today::date()) {
            throw ValidationException::withMessages(['period' => __(':month has not ended yet.', ['month' => $month->name()])]);
        }

        return DB::transaction(function () use ($owner, $month, $cash, $text, $direction, $note): string {
            Periods::lockExclusive();

            if ($month->isClosed()) {
                throw ValidationException::withMessages(['period' => __(':month is closed.', ['month' => $month->name()])]);
            }

            $amount = Decimal::money($text);
            $this->assertMatchesCount($month, $cash, $amount, $direction);

            $period = $month->periodOrCreate();
            $voucher = VoucherNumberService::next('cash_adjustment');
            $label = $direction === self::SHORTAGE ? 'Cash shortage' : 'Cash excess';
            $description = "{$label} {$voucher} – {$cash->name}: {$note}";

            $journal = PostingService::post($direction === self::SHORTAGE
                ? PostingRules::pr23CashShortage($period, $cash, $amount, $description, $month->lastDay())
                : PostingRules::pr24CashExcess($period, $cash, $amount, $description, $month->lastDay()), $owner);

            AuditLogger::log(AuditActions::CREATED, $period, [], [
                'cash_adjustment' => $voucher,
                'money_account_id' => $cash->id,
                'direction' => $direction,
                'amount' => $amount,
                'journal_entry_id' => $journal->id,
            ], reason: $note, by: $owner);

            return $voucher;
        });
    }

    /** The latest count on the last day, minus the ledger, must be this shortage or excess. */
    private function assertMatchesCount(Month $month, MoneyAccount $cash, string $amount, string $direction): void
    {
        $count = CashCounts::latest($cash, $month->lastDay());

        if ($count === null || $count->business_closed || $count->counted_amount === null) {
            throw ValidationException::withMessages(['amount' => __('Enter the cash count for :date first.', ['date' => Dates::display($month->lastDay())])]);
        }

        $difference = Decimal::sub($count->counted_amount, $cash->balance($month->lastDay()));
        $expected = $direction === self::SHORTAGE ? Decimal::negate($amount) : $amount;

        if (Decimal::compare($difference, $expected) !== 0) {
            throw ValidationException::withMessages(['amount' => Decimal::isZero($difference)
                ? __('There is no cash difference to post.')
                : __('The cash difference is :amount. Post exactly that amount.', ['amount' => NumberFormat::money(Decimal::abs($difference)).(Decimal::isNegative($difference) ? ' short' : ' extra')])]);
        }
    }
}

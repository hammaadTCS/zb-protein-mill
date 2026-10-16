<?php

declare(strict_types=1);

namespace App\Domain\Reports\Documents;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Decimal;
use App\Support\EntryStatus;
use App\Support\Format;

/**
 * Spec §13.2 entry slip: one purchase or sale for the party, with their balance
 * right after it. "Our weight" is internal and never shown (P).
 */
final class EntrySlip
{
    /**
     * @return array<string, mixed> the view data of `pdf.slip`
     */
    public static function data(Purchase|Sale $entry): array
    {
        $entry->loadMissing(['party', 'item']);
        $isSale = $entry instanceof Sale;
        $payments = Payment::query()
            ->where($isSale ? 'sale_id' : 'purchase_id', $entry->id)
            ->where('status', EntryStatus::Active->value)
            ->whereIn('approval_status', [ApprovalStatus::NotRequired->value, ApprovalStatus::Approved->value])
            ->get();
        $paidNow = Decimal::sum($payments->map(fn (Payment $p): string => $p->amount)->all());

        return [
            'company' => (string) Settings::get(SettingKeys::COMPANY_NAME),
            'logo' => PartyStatement::logo(),
            'title' => $isSale ? 'Sale Slip' : 'Purchase Slip',
            'voucher' => $entry->voucher_no,
            'date' => Format::date($entry->entry_date),
            'party' => ['name' => $entry->party->name, 'code' => $entry->party->code],
            'item' => $entry->item->name,
            'kg' => Format::kg($entry->kg),
            'rate' => $entry->rate_status === RateStatus::Pending || $entry->rate === null ? 'Rate pending' : Format::rate($entry->rate),
            'amount' => $entry->amount === null ? null : Format::money($entry->amount),
            'slip_no' => $entry->slip_no,
            'money_now_label' => $isSale ? 'Received now' : 'Paid now',
            'money_now' => Decimal::isZero($paidNow) ? null : Format::money($paidNow),
            'balance' => self::balanceAfter($entry, $payments->map(fn (Payment $p): ?int => $p->journal_entry_id)->filter()->all()),
            'generated' => 'Generated on '.Format::now(),
        ];
    }

    /**
     * The party's balance in words right after this entry and its payment: every
     * posting before the entry's date, and on that date up to its own journals.
     *
     * @param  array<int, int|null>  $paymentJournals
     */
    private static function balanceAfter(Purchase|Sale $entry, array $paymentJournals): string
    {
        $party = $entry->party;
        $code = (string) $party->type->controlAccountCode();
        $last = max(array_filter([$entry->journal_entry_id, ...$paymentJournals]) ?: [0]);

        $net = (string) JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->where('journal_lines.party_id', $party->id)
            ->where('ledger_accounts.code', $code)
            ->where(fn ($q) => $q->where('journal_entries.posting_date', '<', $entry->entry_date)
                ->orWhere(fn ($same) => $same->where('journal_entries.posting_date', $entry->entry_date)->where('journal_entries.id', '<=', $last)))
            ->toBase()
            ->selectRaw('CAST(COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0) AS DECIMAL(20,2)) AS net')
            ->value('net');

        $balance = $party->balanceOfNet($net);

        return PartyStatement::words($balance->words, $balance->amount);
    }
}

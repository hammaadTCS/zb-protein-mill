<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Support\Decimal;
use App\Support\Tenancy\CurrentCompany;

/**
 * R-09 partner statement (page 23): per partner, money invested (3000 Partner Capital,
 * opening capital included), withdrawn (3100 Partner Withdrawals) and the net, from
 * the journal lines posted in the date range. No profit is worked out (BR-PTN-04).
 */
final class PartnerStatement
{
    /**
     * @return list<array{party_id: int, code: string, name: string, invested: string, withdrawn: string, net: string}>
     */
    public static function between(string $from, string $to, ?int $partyId = null): array
    {
        $sums = JournalLine::query()
            ->withoutGlobalScope('company')
            ->toBase()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->where('journal_entries.company_id', CurrentCompany::id())
            ->whereBetween('journal_entries.posting_date', [$from, $to])
            ->whereIn('ledger_accounts.code', ['3000', '3100'])
            ->whereNotNull('journal_lines.party_id')
            ->groupBy('journal_lines.party_id')
            ->select('journal_lines.party_id')
            // Reversals of cancelled or edited entries cancel out inside each sum.
            ->selectRaw("CAST(COALESCE(SUM(CASE WHEN ledger_accounts.code = '3000' THEN journal_lines.credit - journal_lines.debit END), 0) AS DECIMAL(16,2)) AS invested")
            ->selectRaw("CAST(COALESCE(SUM(CASE WHEN ledger_accounts.code = '3100' THEN journal_lines.debit - journal_lines.credit END), 0) AS DECIMAL(16,2)) AS withdrawn")
            ->get()
            ->keyBy('party_id');

        $rows = [];

        foreach (Party::query()->where('type', PartyType::Partner->value)->when($partyId, fn ($q, int $id) => $q->whereKey($id))->orderBy('code')->get() as $partner) {
            $sum = $sums->get($partner->id);
            $invested = $sum === null ? '0.00' : (string) $sum->invested;
            $withdrawn = $sum === null ? '0.00' : (string) $sum->withdrawn;

            $rows[] = [
                'party_id' => $partner->id,
                'code' => $partner->code,
                'name' => $partner->name,
                'invested' => $invested,
                'withdrawn' => $withdrawn,
                'net' => Decimal::sub($invested, $withdrawn),
            ];
        }

        return $rows;
    }
}

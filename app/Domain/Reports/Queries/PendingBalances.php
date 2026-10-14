<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Reports\Ageing;
use App\Domain\Sales\Models\Sale;
use App\Support\Decimal;
use App\Support\Tenancy\CurrentCompany;

/**
 * R-02 pending balances (page 33): who owes us, whom we owe and advances, as of a
 * date, from each party's control account. Partners are not parties with dues.
 */
final class PendingBalances
{
    public const SECTIONS = ['Owes us', 'We owe', 'Advance'];

    /**
     * @return array<string, array{rows: list<array{id: int, code: string, name: string, type: string, words: string, amount: string, pending_kg: string, advance_age_days: int|null, overdue: string|null}>, total: string}>
     */
    public static function asOf(string $date, ?PartyType $type = null): array
    {
        $nets = JournalLine::query()
            ->withoutGlobalScope('company')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->join('parties', 'parties.id', '=', 'journal_lines.party_id')
            ->where('journal_entries.company_id', CurrentCompany::id())
            ->where('journal_entries.posting_date', '<=', $date)
            ->whereRaw("ledger_accounts.code = CASE parties.type WHEN 'supplier' THEN '2000' WHEN 'buyer' THEN '1200' WHEN 'employee' THEN '1300' WHEN 'other' THEN '2100' END")
            ->groupBy('journal_lines.party_id')
            ->toBase()
            ->selectRaw('journal_lines.party_id, (SUM(journal_lines.debit) - SUM(journal_lines.credit))::numeric(20,2)::text AS net')
            ->pluck('net', 'party_id');

        $pendingKg = self::pendingKg($date);
        $sections = array_fill_keys(self::SECTIONS, ['rows' => [], 'total' => '0.00']);

        $parties = Party::query()
            ->whereIn('id', array_keys($nets->all()))
            ->where('type', '!=', PartyType::Partner->value)
            ->when($type, fn ($q, PartyType $t) => $q->where('type', $t->value))
            ->orderBy('code')
            ->get();

        // Overdue for every buyer on the report, in one query (spec §12.6, T9.2).
        $overdue = Ageing::overdueMany($parties->filter(fn (Party $party): bool => $party->type === PartyType::Buyer), $date);

        foreach ($parties as $party) {
            $balance = $party->balanceOfNet((string) $nets[$party->id], $pendingKg[$party->id] ?? '0.000');

            if (! isset($sections[$balance->words])) {
                continue; // settled
            }

            $sections[$balance->words]['rows'][] = [
                'id' => $party->id,
                'code' => $party->code,
                'name' => $party->name,
                'type' => $party->type->value,
                'words' => $balance->words,
                'amount' => $balance->amount,
                'pending_kg' => $balance->pendingKg,
                'advance_age_days' => $balance->words === 'Advance' ? Ageing::advanceAgeDays($party, $date) : null,
                'overdue' => $party->type === PartyType::Buyer && $balance->words === 'Owes us' ? $overdue[$party->id] : null,
            ];
            $sections[$balance->words]['total'] = Decimal::add($sections[$balance->words]['total'], $balance->amount);
        }

        return $sections;
    }

    /**
     * Rate-pending kg per party of active entries dated on or before the date.
     *
     * @return array<int, string>
     */
    private static function pendingKg(string $date): array
    {
        $kg = [];

        foreach ([Purchase::class, Sale::class] as $model) {
            $rows = $model::query()
                ->where('status', 'active')
                ->where('rate_status', 'pending')
                ->where('entry_date', '<=', $date)
                ->groupBy('party_id')
                ->selectRaw('party_id, SUM(kg)::numeric(20,3)::text AS kg')
                ->pluck('kg', 'party_id');

            foreach ($rows as $partyId => $sum) {
                $kg[(int) $partyId] = (string) $sum;
            }
        }

        return $kg;
    }
}

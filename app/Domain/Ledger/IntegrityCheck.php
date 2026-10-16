<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Reports\Queries\TrialBalance;
use App\Support\Today;
use Illuminate\Support\Facades\DB;

/**
 * BR-OPS-08, ADR-0002 (card T7.8): an independent check that the books are whole, on
 * the live database or on a restored copy. Read-only: SELECTs only. Each check reports
 * the IDs involved (journal ids, voucher numbers, record type:id), never names or
 * amounts. Faults are never fixed here; a person investigates (runbook §5).
 */
final class IntegrityCheck
{
    /** Posted business records: table => morph alias (conventions §7). */
    private const ENTRY_TABLES = [
        'purchases' => 'purchase',
        'sales' => 'sale',
        'payments' => 'payment',
        'expenses' => 'expense',
        'adjustments' => 'adjustment',
        'partner_transactions' => 'partner_transaction',
        'transfers' => 'transfer',
        'opening_balances' => 'opening_balance',
        'rate_corrections' => 'rate_correction',
    ];

    /** Where each voucher sequence's numbers are kept: doc type => [table, column, extra condition]. */
    private const VOUCHER_SOURCES = [
        'purchase' => ['purchases', 'voucher_no', null],
        'sale' => ['sales', 'voucher_no', null],
        'adjustment' => ['adjustments', 'voucher_no', null],
        'payment_paid' => ['payments', 'voucher_no', "voucher_no LIKE 'PAY-%'"],
        'payment_received' => ['payments', 'voucher_no', "voucher_no LIKE 'RCV-%'"],
        'expense' => ['expenses', 'voucher_no', null],
        'partner_transaction' => ['partner_transactions', 'voucher_no', null],
        'transfer' => ['transfers', 'voucher_no', null],
        'opening_balance' => ['opening_balances', 'voucher_no', null],
        'rate_correction' => ['rate_corrections', 'voucher_no', null],
        'journal' => ['journal_entries', 'entry_no', null],
        // Conventions §7: a month-end cash adjustment keeps its CSH voucher in the journal description.
        'cash_adjustment' => ['journal_entries', "substring(description from 'CSH-[0-9]{6}')", "source_type = 'month_end_adjustment' AND reversal_of_id IS NULL"],
        'party_supplier' => ['parties', 'code', "type = 'supplier'"],
        'party_buyer' => ['parties', 'code', "type = 'buyer'"],
        'party_partner' => ['parties', 'code', "type = 'partner'"],
        'party_employee' => ['parties', 'code', "type = 'employee'"],
        'party_other' => ['parties', 'code', "type = 'other'"],
    ];

    /**
     * @return list<array{check: string, ok: bool, ids: list<string>}>
     */
    public static function run(): array
    {
        return [
            self::result('journals_balance', self::unbalancedJournals()),
            self::result('seals', self::seals()),
            self::result('trial_balance', self::trialBalance()),
            self::result('voucher_numbers', self::voucherNumbers()),
            self::result('control_account_parties', self::controlAccountParties()),
            self::result('entry_journals', self::entryJournals()),
        ];
    }

    /**
     * 1. Every journal entry: debit total = credit total.
     *
     * @return list<string>
     */
    private static function unbalancedJournals(): array
    {
        return self::column(DB::select(
            'SELECT journal_entry_id AS id FROM journal_lines GROUP BY journal_entry_id HAVING SUM(debit) <> SUM(credit) ORDER BY journal_entry_id'
        ), 'journal ');
    }

    /**
     * 2. Every journal entry carries a seal row that still matches its lines (BR-LED-11). The
     * seal records the entry's line count and its debit and credit totals when the entry was
     * saved; a line added or removed afterwards — or an entry written without a seal at all —
     * leaves a difference here. The seal row itself is refused UPDATE and DELETE by a trigger,
     * so this is the check that catches a change made with the application's own database user.
     *
     * @return list<string>
     */
    private static function seals(): array
    {
        // The lines are summed in a subquery so the comparison works under ONLY_FULL_GROUP_BY,
        // and a LEFT JOIN finds an entry whose seal row is missing. ("lines" is a MariaDB
        // keyword, so the subquery is named "summed".)
        return self::column(DB::select(
            'SELECT summed.journal_entry_id AS id
             FROM (
                 SELECT journal_entry_id, COUNT(*) AS line_count, SUM(debit) AS total_debit, SUM(credit) AS total_credit
                 FROM journal_lines
                 GROUP BY journal_entry_id
             ) AS summed
             LEFT JOIN journal_seals AS seal ON seal.journal_entry_id = summed.journal_entry_id
             WHERE seal.id IS NULL
                OR summed.line_count <> seal.line_count
                OR summed.total_debit <> seal.total_debit
                OR summed.total_credit <> seal.total_credit
             ORDER BY summed.journal_entry_id'
        ), 'journal ');
    }

    /**
     * 3. Trial balance totals are equal (T1.10 query), for each company.
     *
     * @return list<string>
     */
    private static function trialBalance(): array
    {
        $failed = [];

        foreach (DB::select('SELECT id FROM companies ORDER BY id') as $company) {
            // The query is scoped to the current company; version 1 has one (CurrentCompany).
            if (! TrialBalance::asOf(Today::date())->balanced) {
                $failed[] = "company {$company->id}";
            }
        }

        return $failed;
    }

    /**
     * 4. Voucher numbers 1 to next_number − 1, each once, for every document type.
     *
     * @return list<string>
     */
    private static function voucherNumbers(): array
    {
        $faults = [];

        foreach (DB::select('SELECT company_id, doc_type, prefix, next_number FROM voucher_sequences ORDER BY company_id, doc_type') as $sequence) {
            [$table, $column, $where] = self::VOUCHER_SOURCES[$sequence->doc_type] ?? [null, null, null];
            $width = VoucherNumberService::DOC_TYPES[$sequence->doc_type][1] ?? 6;

            if ($table === null) {
                $faults[] = "{$sequence->doc_type} unknown";

                continue;
            }

            $used = array_map(fn (object $row): string => (string) ((array) $row)['voucher'], DB::select(
                "SELECT {$column} AS voucher FROM {$table} WHERE company_id = ?".($where !== null ? " AND {$where}" : ''),
                [$sequence->company_id],
            ));
            $counts = array_count_values(array_filter($used, fn (string $v): bool => $v !== ''));
            $label = fn (int $n): string => $sequence->prefix.'-'.str_pad((string) $n, $width, '0', STR_PAD_LEFT);

            for ($n = 1; $n < (int) $sequence->next_number; $n++) {
                $count = $counts[$label($n)] ?? 0;

                if ($count === 0) {
                    $faults[] = $label($n).' missing';
                } elseif ($count > 1) {
                    $faults[] = $label($n).' duplicated';
                }

                unset($counts[$label($n)]);
            }

            foreach (array_keys($counts) as $extra) {
                $faults[] = "{$extra} beyond the sequence";
            }
        }

        return $faults;
    }

    /**
     * 5. Lines on a control account (ledger account with a party type) carry a party;
     * no other line does.
     *
     * @return list<string>
     */
    private static function controlAccountParties(): array
    {
        return self::column(DB::select(<<<'SQL'
            SELECT DISTINCT l.journal_entry_id AS id
            FROM journal_lines l
            JOIN ledger_accounts a ON a.id = l.ledger_account_id
            WHERE (a.party_type IS NOT NULL AND l.party_id IS NULL)
               OR (a.party_type IS NULL AND l.party_id IS NOT NULL)
            ORDER BY l.journal_entry_id
            SQL), 'journal ');
    }

    /**
     * 6. An active posted entry points to a journal of its own; a cancelled posted entry
     * has a reversal journal.
     *
     * @return list<string>
     */
    private static function entryJournals(): array
    {
        $faults = [];

        foreach (self::ENTRY_TABLES as $table => $alias) {
            $hasStatus = $table !== 'opening_balances' && $table !== 'rate_corrections';
            $active = $hasStatus ? "AND t.status = 'active'" : '';

            foreach (DB::select(<<<SQL
                SELECT t.id FROM {$table} t
                JOIN journal_entries j ON j.id = t.journal_entry_id
                WHERE t.journal_entry_id IS NOT NULL {$active}
                  AND (j.source_type <> ? OR j.source_id <> t.id)
                ORDER BY t.id
                SQL, [$alias]) as $row) {
                $faults[] = "{$alias}:{$row->id} journal of another record";
            }

            if (! $hasStatus) {
                continue;
            }

            foreach (DB::select(<<<SQL
                SELECT t.id FROM {$table} t
                WHERE t.status = 'cancelled' AND t.journal_entry_id IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM journal_entries r WHERE r.reversal_of_id = t.journal_entry_id)
                ORDER BY t.id
                SQL) as $row) {
                $faults[] = "{$alias}:{$row->id} cancelled without reversal";
            }
        }

        return $faults;
    }

    /**
     * @param  list<string>  $ids
     * @return array{check: string, ok: bool, ids: list<string>}
     */
    private static function result(string $check, array $ids): array
    {
        return ['check' => $check, 'ok' => $ids === [], 'ids' => $ids];
    }

    /**
     * @param  array<int, object>  $rows
     * @return list<string>
     */
    private static function column(array $rows, string $prefix): array
    {
        return array_values(array_map(fn (object $row): string => $prefix.((array) $row)['id'], $rows));
    }
}

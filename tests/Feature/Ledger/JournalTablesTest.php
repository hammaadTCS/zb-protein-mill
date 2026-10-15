<?php

use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Inserts a journal straight through the models, with the posting flag set the way
 * PostingService sets it, then seals it (sealJournal). Lines are [account code, debit, credit].
 *
 * @param  list<array{string, string, string}>  $lines
 */
function insertJournalThroughModels(array $lines): JournalEntry
{
    $user = User::factory()->create();

    return withPostingFlag(function () use ($lines, $user) {
        $entry = JournalEntry::query()->create([
            'company_id' => $user->company_id,
            'entry_no' => 'JV-T00001',
            'posting_date' => '2026-01-05',
            'source_type' => 'test',
            'source_id' => 1,
            'description' => 'Test journal',
            'created_by' => $user->id,
        ]);

        foreach ($lines as $i => [$code, $debit, $credit]) {
            JournalLine::query()->create([
                'company_id' => $user->company_id,
                'journal_entry_id' => $entry->id,
                'line_no' => $i + 1,
                'ledger_account_id' => LedgerAccount::query()->where('code', $code)->value('id'),
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }

        sealJournal($entry);

        return $entry;
    });
}

/**
 * Writes the entry's seal row the way PostingService does at the end of its save (BR-LED-11).
 * The BEFORE INSERT trigger on journal_seals sums the entry's journal_lines and refuses the
 * whole save (SIGNAL SQLSTATE '45000') when there are fewer than two lines or debit != credit
 * — the MariaDB replacement for the deferred journal_balance_check constraint trigger.
 */
function sealJournal(JournalEntry $entry): void
{
    $totals = DB::table('journal_lines')->where('journal_entry_id', $entry->id)
        ->selectRaw('COUNT(*) AS line_count, COALESCE(SUM(debit), 0) AS total_debit, COALESCE(SUM(credit), 0) AS total_credit')
        ->first();

    DB::table('journal_seals')->insert([
        'company_id' => $entry->company_id,
        'journal_entry_id' => $entry->id,
        'line_count' => $totals->line_count,
        'total_debit' => $totals->total_debit,
        'total_credit' => $totals->total_credit,
        'sealed_at' => now(),
    ]);
}

it('T-02 database refuses an unbalanced journal', function () {
    insertJournalThroughModels([['5000', '100.00', '0.00'], ['3900', '0.00', '90.00']]);
})->throws(QueryException::class, 'is not balanced');

it('T-02 database refuses a journal with only one line', function () {
    insertJournalThroughModels([['5000', '100.00', '0.00']]);
})->throws(QueryException::class, 'fewer than 2 lines');

it('T-02 database accepts a balanced journal', function () {
    $entry = insertJournalThroughModels([['5000', '100.00', '0.00'], ['3900', '0.00', '100.00']]);

    expect(JournalLine::query()->where('journal_entry_id', $entry->id)->count())->toBe(2);
});

it('T-03 journal rows cannot be updated or deleted', function (string $table, string $operation) {
    $entry = insertJournalThroughModels([['5000', '100.00', '0.00'], ['3900', '0.00', '100.00']]);

    $query = DB::table($table)->where($table === 'journal_entries' ? 'id' : 'journal_entry_id', $entry->id);

    expect(fn () => $operation === 'update'
        ? $query->update(['company_id' => $entry->company_id])
        : $query->delete()
    )->toThrow(QueryException::class, "Rows in {$table} cannot be updated or deleted");
})->with([
    'update entry' => ['journal_entries', 'update'],
    'delete entry' => ['journal_entries', 'delete'],
    'update line' => ['journal_lines', 'update'],
    'delete line' => ['journal_lines', 'delete'],
]);

it('BR-LED-02 a line with both debit and credit, or neither, is refused', function (string $debit, string $credit) {
    insertJournalThroughModels([['5000', $debit, $credit], ['3900', '0.00', '100.00']]);
})->with([
    'both sides' => ['100.00', '100.00'],
    'neither side' => ['0.00', '0.00'],
    'negative debit' => ['-100.00', '0.00'],
])->throws(QueryException::class, 'journal_lines_one_side');

it('journal rows cannot be created outside PostingService', function () {
    $user = User::factory()->create();

    // Both refusals happen in PHP before any SQL runs, so one test checks both models.
    expect(fn () => JournalEntry::query()->create(['company_id' => $user->company_id]))
        ->toThrow(LogicException::class, 'Journal rows can only be created by PostingService')
        ->and(fn () => JournalLine::query()->create(['company_id' => $user->company_id]))
        ->toThrow(LogicException::class, 'Journal rows can only be created by PostingService');
});

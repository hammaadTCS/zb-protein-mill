<?php

use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Inserts a journal straight through the models, with the posting flag set the way
 * PostingService sets it. Lines are [account code, debit, credit].
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

        return $entry;
    });
}

it('T-02 database refuses an unbalanced journal', function () {
    insertJournalThroughModels([['5000', '100.00', '0.00'], ['3900', '0.00', '90.00']]);

    DB::statement('SET CONSTRAINTS journal_balance_check IMMEDIATE');
})->throws(QueryException::class, 'is not balanced');

it('T-02 database refuses a journal with only one line', function () {
    insertJournalThroughModels([['5000', '100.00', '0.00']]);

    DB::statement('SET CONSTRAINTS journal_balance_check IMMEDIATE');
})->throws(QueryException::class, 'fewer than 2 lines');

it('T-02 database accepts a balanced journal', function () {
    $entry = insertJournalThroughModels([['5000', '100.00', '0.00'], ['3900', '0.00', '100.00']]);

    DB::statement('SET CONSTRAINTS journal_balance_check IMMEDIATE');

    expect(JournalLine::query()->where('journal_entry_id', $entry->id)->count())->toBe(2);
});

it('T-03 journal rows cannot be updated or deleted', function (string $table, string $operation) {
    $entry = insertJournalThroughModels([['5000', '100.00', '0.00'], ['3900', '0.00', '100.00']]);
    DB::statement('SET CONSTRAINTS journal_balance_check IMMEDIATE');

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

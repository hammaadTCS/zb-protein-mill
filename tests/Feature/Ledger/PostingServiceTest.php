<?php

use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\NormalSide;
use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\PostingLine;
use App\Domain\Ledger\PostingRequest;
use App\Domain\Ledger\PostingService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * @param  list<array{0: string, 1: string, 2: string, 3?: int|null}>  $lines  [code, side, amount, party id]
 */
function testPosting(array $lines, string $date = '2026-01-05'): PostingRequest
{
    return new PostingRequest(
        postingDate: $date,
        sourceType: 'test',
        sourceId: 1,
        description: 'Test journal',
        lines: array_map(fn (array $l) => new PostingLine(
            accountCode: $l[0],
            side: $l[1],
            amount: $l[2],
            partyId: $l[3] ?? null,
        ), $lines),
    );
}

it('PostingService posts a balanced two-line journal with JV-000001', function () {
    $user = User::factory()->create();

    $entry = DB::transaction(fn () => PostingService::post(
        testPosting([['5000', 'debit', '40000.00'], ['3900', 'credit', '40000.00']]),
        $user,
    ));

    $lines = $entry->lines()->with('account')->get()
        ->map(fn (JournalLine $l) => [$l->line_no, $l->account->code, $l->debit, $l->credit])
        ->all();

    expect($entry->entry_no)->toBe('JV-000001')
        ->and($entry->posting_date)->toBe('2026-01-05')
        ->and($entry->created_by)->toBe($user->id)
        ->and($lines)->toBe([[1, '5000', '40000.00', '0.00'], [2, '3900', '0.00', '40000.00']])
        ->and(PostingService::isPosting())->toBeFalse();
});

it('BR-LED-03 an unbalanced request is refused before touching the database', function () {
    $user = User::factory()->create();

    expect(fn () => DB::transaction(fn () => PostingService::post(
        testPosting([['5000', 'debit', '100.00'], ['3900', 'credit', '90.00']]),
        $user,
    )))->toThrow(InvalidArgumentException::class, 'debit 100.00 does not equal credit 90.00');

    expect(JournalEntry::query()->count())->toBe(0)
        ->and(DB::table('voucher_sequences')->where('doc_type', 'journal')->exists())->toBeFalse()
        ->and(PostingService::isPosting())->toBeFalse();
});

it('BR-LED-05 reverse swaps every line and nets each account to zero', function () {
    $user = User::factory()->create();

    [$original, $reversal] = DB::transaction(function () use ($user) {
        $original = PostingService::post(testPosting([
            ['5000', 'debit', '28800.00'],
            ['3900', 'credit', '18800.00'],
            ['3900', 'credit', '10000.00'],
        ]), $user);

        return [$original, PostingService::reverse($original, '2026-01-06', 'Reversal of test', $user)];
    });

    $swapped = $reversal->lines()->with('account')->get()
        ->map(fn (JournalLine $l) => [$l->account->code, $l->debit, $l->credit])
        ->all();
    $net = JournalLine::query()
        ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
        ->groupBy('ledger_accounts.code')
        ->orderBy('ledger_accounts.code')
        ->selectRaw('ledger_accounts.code, CAST(SUM(debit) - SUM(credit) AS CHAR) AS net')
        ->pluck('net', 'code')
        ->all();

    expect($reversal->entry_no)->toBe('JV-000002')
        ->and($reversal->reversal_of_id)->toBe($original->id)
        ->and($reversal->posting_date)->toBe('2026-01-06')
        ->and($reversal->source_type)->toBe($original->source_type)
        ->and($reversal->source_id)->toBe($original->source_id)
        ->and($swapped)->toBe([
            ['5000', '0.00', '28800.00'],
            ['3900', '18800.00', '0.00'],
            ['3900', '10000.00', '0.00'],
        ])
        ->and($net)->toBe(['3900' => '0.00', '5000' => '0.00']);
});

it('a journal cannot be reversed twice', function () {
    $user = User::factory()->create();

    DB::transaction(function () use ($user) {
        $original = PostingService::post(testPosting([['5000', 'debit', '10.00'], ['3900', 'credit', '10.00']]), $user);
        PostingService::reverse($original, '2026-01-05', 'First reversal', $user);
        PostingService::reverse($original, '2026-01-05', 'Second reversal', $user);
    });
})->throws(LogicException::class, 'already been reversed');

it('BR-LED-04 a line on 2000 without a party is refused; a line on 5000 with a party is refused', function (array $lines, string $message) {
    expect(fn () => DB::transaction(fn () => PostingService::post(testPosting($lines), User::factory()->create())))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'suppliers without party' => [[['5000', 'debit', '50.00'], ['2000', 'credit', '50.00']], 'Account 2000 needs a party'],
    'purchases with a party' => [[['5000', 'debit', '50.00', 1], ['3900', 'credit', '50.00']], 'Account 5000 cannot carry a party'],
]);

it('PostingService refuses a badly formed request', function (array $lines, string $message) {
    expect(fn () => DB::transaction(fn () => PostingService::post(testPosting($lines), User::factory()->create())))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'one line' => [[['5000', 'debit', '50.00']], 'at least 2 lines'],
    'zero amount' => [[['5000', 'debit', '0.00'], ['3900', 'credit', '0.00']], 'positive amount with 2 decimals'],
    'three decimals' => [[['5000', 'debit', '50.005'], ['3900', 'credit', '50.005']], 'positive amount with 2 decimals'],
    'no decimals' => [[['5000', 'debit', '50'], ['3900', 'credit', '50']], 'positive amount with 2 decimals'],
    'unknown account' => [[['9999', 'debit', '50.00'], ['3900', 'credit', '50.00']], 'Account 9999 does not exist'],
    'unknown side' => [[['5000', 'left', '50.00'], ['3900', 'credit', '50.00']], 'Side must be debit or credit'],
]);

it('PostingService refuses an inactive account', function () {
    $user = User::factory()->create();
    LedgerAccount::query()->create([
        'company_id' => $user->company_id, 'code' => '1010', 'name' => 'Cash – Old', 'type' => AccountType::Asset,
        'normal_side' => NormalSide::Debit, 'is_system' => false, 'is_active' => false,
    ]);

    DB::transaction(fn () => PostingService::post(testPosting([['1010', 'debit', '50.00'], ['3900', 'credit', '50.00']]), $user));
})->throws(InvalidArgumentException::class, 'Account 1010 is inactive');

it('PostingService refuses to post outside a database transaction', function () {
    // Build the user first: the second connection cannot see this test's data.
    $user = User::factory()->create();
    config(['database.connections.mysql_outside' => config('database.connections.mysql')]);
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('mysql_outside');

    try {
        expect(fn () => PostingService::post(testPosting([['5000', 'debit', '1.00'], ['3900', 'credit', '1.00']]), $user))
            ->toThrow(LogicException::class, 'inside a database transaction');
    } finally {
        DB::setDefaultConnection($default);
        DB::purge('mysql_outside');
    }
});

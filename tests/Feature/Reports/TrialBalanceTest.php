<?php

use App\Domain\Ledger\PostingLine;
use App\Domain\Ledger\PostingRequest;
use App\Domain\Ledger\PostingService;
use App\Domain\Reports\Queries\TrialBalance;
use App\Domain\Reports\Queries\TrialBalanceRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Three balanced journals on accounts without parties:
 *   05-01: 5000 Dr 40,000 / 3900 Cr 40,000
 *   06-01: 3900 Dr 10,000 / 4000 Cr 10,000
 *   07-01: 4900 Dr  2,000 / 3900 Cr  2,000
 */
beforeEach(function () {
    $user = User::factory()->create();

    DB::transaction(function () use ($user) {
        foreach ([
            ['2026-01-05', '5000', '3900', '40000.00'],
            ['2026-01-06', '3900', '4000', '10000.00'],
            ['2026-01-07', '4900', '3900', '2000.00'],
        ] as [$date, $debit, $credit, $amount]) {
            PostingService::post(new PostingRequest($date, 'test', 1, 'Test', [
                PostingLine::debit($debit, $amount),
                PostingLine::credit($credit, $amount),
            ]), $user);
        }
    });
});

/** @return list<array{string, string, string, string, string}> */
function trialRows(array $rows): array
{
    return array_map(fn (TrialBalanceRow $r) => [$r->code, $r->debitTotal, $r->creditTotal, $r->balanceDebit, $r->balanceCredit], $rows);
}

it('R-07 trial balance of hand-made journals balances', function () {
    $tb = TrialBalance::asOf('2026-01-31');

    expect(trialRows($tb->rows))->toBe([
        ['3900', '10000.00', '42000.00', '0.00', '32000.00'],
        ['4000', '0.00', '10000.00', '0.00', '10000.00'],
        ['4900', '2000.00', '0.00', '2000.00', '0.00'],
        ['5000', '40000.00', '0.00', '40000.00', '0.00'],
    ])
        ->and($tb->rows[0]->name)->toBe('Opening Balance Equity')
        ->and($tb->totalDebit)->toBe('42000.00')
        ->and($tb->totalCredit)->toBe('42000.00')
        ->and($tb->balanced)->toBeTrue();
});

it('R-07 as-of date excludes later postings', function () {
    $tb = TrialBalance::asOf('2026-01-06');

    expect(trialRows($tb->rows))->toBe([
        ['3900', '10000.00', '40000.00', '0.00', '30000.00'],
        ['4000', '0.00', '10000.00', '0.00', '10000.00'],
        ['5000', '40000.00', '0.00', '40000.00', '0.00'],
    ])
        ->and($tb->totalDebit)->toBe('40000.00')
        ->and($tb->totalCredit)->toBe('40000.00')
        ->and($tb->balanced)->toBeTrue();
});

it('R-07 an account whose lines net to zero is kept with a zero balance', function () {
    $user = User::factory()->create();
    DB::transaction(fn () => PostingService::post(new PostingRequest('2026-01-08', 'test', 1, 'Test', [
        PostingLine::debit('4000', '10000.00'),
        PostingLine::credit('5900', '10000.00'),
    ]), $user));

    $row = collect(TrialBalance::asOf('2026-01-31')->rows)->firstWhere('code', '4000');

    expect([$row->debitTotal, $row->creditTotal, $row->balanceDebit, $row->balanceCredit])
        ->toBe(['10000.00', '10000.00', '0.00', '0.00']);
});

it('R-07 before the first posting the trial balance is empty and balanced', function () {
    $tb = TrialBalance::asOf('2026-01-04');

    expect($tb->rows)->toBe([])
        ->and($tb->totalDebit)->toBe('0.00')
        ->and($tb->balanced)->toBeTrue();
});

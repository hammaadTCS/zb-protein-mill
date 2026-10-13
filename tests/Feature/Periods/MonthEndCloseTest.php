<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Checks\Actions\CreateCashCount;
use App\Domain\Items\Models\Item;
use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Periods\Actions\CloseMonth;
use App\Domain\Periods\Actions\PostCashDifference;
use App\Domain\Periods\Actions\RecordBankStatement;
use App\Domain\Periods\Models\PeriodEvent;
use App\Domain\Periods\Month;
use App\Domain\Periods\MonthCloseResult;
use App\Domain\Periods\MonthEndCheck;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Reports\Queries\AccountStatement;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AcceptanceRunner;

/**
 * @return list<string>
 */
function failingChecks(MonthCloseResult $result): array
{
    return array_map(fn (MonthEndCheck $check): string => $check->check, $result->failing());
}

it('AT-3 month-end blocks with the exact differences, then closes after the fixes', function () {
    AcceptanceRunner::load('controls', 'AT-3')->setUp()->runAll()->assertExpected();

    $period = Month::of('2026-01')->period();

    expect(PeriodEvent::query()->where('period_id', $period->id)->pluck('event')->all())->toBe(['closed'])
        ->and(AuditLog::query()->where('action', 'month_closed')->where('auditable_id', $period->id)->count())->toBe(1)
        ->and($period->closed_by)->not->toBeNull()
        ->and($period->closed_at)->not->toBeNull();
});

it('PR-23 the cash shortage posts 6900 against cash on the last day with a CSH voucher', function () {
    [$owner, $accounts] = monthEndWorld();
    app(CreateCashCount::class)->handle($owner, $accounts['cash'], '2026-01-31', Decimal::sub($accounts['cash']->balance(), '1000'));

    $voucher = app(PostCashDifference::class)->handle($owner, '2026-01', $accounts['cash'], '1000', 'shortage', 'Unexplained shortage');
    $journal = JournalEntry::query()->where('source_type', 'month_end_adjustment')->sole();

    expect($voucher)->toBe('CSH-000001')
        ->and($journal->posting_date)->toBe('2026-01-31')
        ->and($journal->source_type)->toBe('month_end_adjustment')
        ->and($journal->source_id)->toBe(Month::of('2026-01')->period()->id)
        ->and($journal->description)->toContain('CSH-000001')
        ->and(journalLines($journal->id))->toBe([
            ['6900', '1000.00', '0.00', null, null],
            [$accounts['cash']->ledgerAccount->code, '0.00', '1000.00', null, null],
        ]);

    $row = collect(AccountStatement::for($accounts['cash'], '2026-01-01', '2026-01-31')['rows'])->last();

    expect($row['voucher'])->toBe('CSH-000001')
        ->and($row['description'])->toBe('Cash shortage CSH-000001 – Cash: Unexplained shortage')
        ->and($row['out'])->toBe('1000.00');
});

it('PR-24 the cash excess posts cash against 6900', function () {
    [$owner, $accounts] = monthEndWorld();
    app(CreateCashCount::class)->handle($owner, $accounts['cash'], '2026-01-31', Decimal::add($accounts['cash']->balance(), '250.50'));

    app(PostCashDifference::class)->handle($owner, '2026-01', $accounts['cash'], '250.50', 'excess', 'Found in the drawer');
    $journal = JournalEntry::query()->where('source_type', 'month_end_adjustment')->sole();

    expect(journalLines($journal->id))->toBe([
        [$accounts['cash']->ledgerAccount->code, '250.50', '0.00', null, null],
        ['6900', '0.00', '250.50', null, null],
    ]);
});

it('BR-MEC-06 a cash difference needs a note, a count, and exactly the remaining difference', function () {
    [$owner, $accounts] = monthEndWorld();
    $post = fn (string $amount, string $direction, string $note) => app(PostCashDifference::class)->handle($owner, '2026-01', $accounts['cash'], $amount, $direction, $note);

    expect(fn () => $post('1000', 'shortage', 'Short'))->toThrow(ValidationException::class, 'Enter the cash count for 31-01-2026 first.');

    app(CreateCashCount::class)->handle($owner, $accounts['cash'], '2026-01-31', Decimal::sub($accounts['cash']->balance(), '1000'));

    expect(fn () => $post('1000', 'shortage', '  '))->toThrow(ValidationException::class, 'Enter a note.')
        ->and(fn () => $post('900', 'shortage', 'Short'))->toThrow(ValidationException::class, 'The cash difference is Rs 1,000.00 short.')
        ->and(fn () => $post('1000', 'excess', 'Short'))->toThrow(ValidationException::class, 'The cash difference is Rs 1,000.00 short.');

    $post('1000', 'shortage', 'Short');

    // A second tap finds no difference left.
    expect(fn () => $post('1000', 'shortage', 'Short'))->toThrow(ValidationException::class, 'There is no cash difference to post.')
        ->and(DB::table('journal_entries')->where('source_type', 'month_end_adjustment')->count())->toBe(1);
});

it('BR-MEC-01 February cannot close before January', function () {
    [$owner, $accounts] = monthEndWorld();
    freezeToday('2026-03-02');
    app(CreateCashCount::class)->handle($owner, $accounts['cash'], '2026-02-28', $accounts['cash']->balance('2026-02-28'));
    app(RecordBankStatement::class)->handle($owner, '2026-02', $accounts['hbl'], $accounts['hbl']->balance('2026-02-28'));

    $result = app(CloseMonth::class)->handle($owner, '2026-02');

    expect($result->closed)->toBeFalse()
        ->and(failingChecks($result))->toBe(['previous_months'])
        ->and($result->failing()[0]->details['open_months'])->toBe(['2026-01'])
        ->and(Month::of('2026-02')->isClosed())->toBeFalse();

    matchJanuary($owner, $accounts);

    expect(app(CloseMonth::class)->handle($owner, '2026-01')->closed)->toBeTrue()
        ->and(app(CloseMonth::class)->handle($owner, '2026-02')->closed)->toBeTrue();
});

it('BR-MEC-02 pending rates block unless accepted', function () {
    [$owner, $accounts, $parties] = monthEndWorld();
    app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-20', 'party_id' => $parties['aslam']->id,
        'item_id' => Item::query()->where('name', 'Waste')->value('id'), 'kg' => '400', 'rate' => null,
    ]);
    matchJanuary($owner, $accounts);

    $blocked = app(CloseMonth::class)->handle($owner, '2026-01');

    expect($blocked->closed)->toBeFalse()
        ->and(failingChecks($blocked))->toBe(['pending_rates'])
        ->and($blocked->failing()[0]->details['vouchers'])->toBe(['PUR-000001'])
        ->and(Month::of('2026-01')->isClosed())->toBeFalse();

    expect(app(CloseMonth::class)->handle($owner, '2026-01', acceptPendingRates: true)->closed)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'month_closed')->sole()->new_values)->toBe(['status' => 'closed', 'accepted_pending_rates' => true]);
});

it('BR-MEC-03 awaiting approval blocks', function () {
    [$owner, $accounts, $parties] = monthEndWorld();
    app(CreatePayment::class)->handle(accountant(), [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-20', 'direction' => 'paid', 'party_id' => $parties['aslam']->id,
        'amount' => '60000', 'money_account_id' => $accounts['hbl']->id, 'reference_no' => 'MEC-03',
    ]);
    matchJanuary($owner, $accounts);

    $result = app(CloseMonth::class)->handle($owner, '2026-01', acceptPendingRates: true);

    expect($result->closed)->toBeFalse()
        ->and(failingChecks($result))->toBe(['awaiting_approval'])
        ->and($result->failing()[0]->details['vouchers'])->toBe(['PAY-000001']);
});

it('BR-MEC-05 BR-MEC-06 a missing statement or count fails; the latest of each counts', function () {
    [$owner, $accounts] = monthEndWorld();

    expect(failingChecks(app(CloseMonth::class)->handle($owner, '2026-01')))->toBe(['bank_match', 'cash_match']);

    app(RecordBankStatement::class)->handle($owner, '2026-01', $accounts['hbl'], '1.00');
    app(CreateCashCount::class)->handle($owner, $accounts['cash'], '2026-01-31', '1.00');
    matchJanuary($owner, $accounts);

    expect(app(CloseMonth::class)->handle($owner, '2026-01')->closed)->toBeTrue();
});

it('BR-MEC-07 a month cannot close before it ends, twice, or before the start month', function () {
    [$owner, $accounts] = monthEndWorld();

    expect(fn () => app(CloseMonth::class)->handle($owner, '2026-02'))->toThrow(ValidationException::class, 'February 2026 has not ended yet.')
        ->and(fn () => app(CloseMonth::class)->handle($owner, '2025-12'))->toThrow(ValidationException::class, 'December 2025 is before the system start date.');

    matchJanuary($owner, $accounts);
    app(CloseMonth::class)->handle($owner, '2026-01');

    expect(fn () => app(CloseMonth::class)->handle($owner, '2026-01'))->toThrow(ValidationException::class, 'January 2026 is already closed.')
        ->and(fn () => app(RecordBankStatement::class)->handle($owner, '2026-01', $accounts['hbl'], '0'))->toThrow(ValidationException::class, 'January 2026 is closed.');
});

it('BR-MEC-05 the bank statement stores the ledger balance and the difference', function () {
    [$owner, $accounts] = monthEndWorld();

    $row = app(RecordBankStatement::class)->handle($owner, '2026-01', $accounts['hbl'], '-250.5');

    expect($row->statement_balance)->toBe('-250.50')
        ->and($row->system_balance)->toBe($accounts['hbl']->balance('2026-01-31'))
        ->and($row->difference)->toBe(Decimal::sub('-250.50', $accounts['hbl']->balance('2026-01-31')))
        ->and(fn () => app(RecordBankStatement::class)->handle($owner, '2026-01', $accounts['cash'], '0'))->toThrow(ValidationException::class, 'Select a bank account.');
});

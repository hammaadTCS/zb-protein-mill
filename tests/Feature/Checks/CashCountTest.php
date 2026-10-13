<?php

use App\Domain\Alerts\Alerts;
use App\Domain\Checks\Actions\CreateCashCount;
use App\Domain\Checks\Actions\DismissCashCount;
use App\Domain\Checks\Models\CashCount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

/** AT-1 day 1 (2026-01-05) through the acceptance runner. */
function at1DayOne(): AcceptanceRunner
{
    $runner = AcceptanceRunner::load('at1')->setUp();
    freezeToday('2026-01-05');

    foreach ($runner->data['entries'] as $entry) {
        if (($entry['date'] ?? null) === '2026-01-05') {
            $runner->run($entry);
        }
    }

    return $runner;
}

it('BR-CHK-02 system amount after AT-1 day 1 is 18900.00', function () {
    $runner = at1DayOne();

    $count = app(CreateCashCount::class)->handle($runner->owner, $runner->accounts['cash'], '2026-01-05', '18900');

    expect($count->system_amount)->toBe('18900.00')
        ->and($count->difference)->toBe('0.00');

    // The system amount on an earlier date leaves out later postings.
    expect(app(CreateCashCount::class)->handle($runner->owner, $runner->accounts['cash'], '2026-01-04', '80000')->system_amount)->toBe('80000.00');
});

it('BR-CHK-03 difference is stored and no journal is created', function () {
    $runner = at1DayOne();
    $journals = DB::table('journal_entries')->count();

    $count = app(CreateCashCount::class)->handle($runner->accountant, $runner->accounts['cash'], '2026-01-05', '18400.50', 'Short at closing');

    expect($count->difference)->toBe('-499.50')
        ->and($count->counted_amount)->toBe('18400.50')
        ->and($count->note)->toBe('Short at closing')
        ->and(DB::table('journal_entries')->count())->toBe($journals)
        ->and(ledgerBalance('1010'))->toBe('18900.00');
});

it('BR-CHK-04 dismissal clears the alert', function () {
    $runner = at1DayOne();
    freezeToday('2026-01-06');

    $keys = fn () => collect(Alerts::for($runner->owner))->pluck('key')->all();

    expect($keys())->toContain('missing_cash_count')
        ->and(collect(Alerts::for($runner->owner))->firstWhere('key', 'missing_cash_count')['label'])->toBe('No cash count for 05-01-2026');

    app(DismissCashCount::class)->handle($runner->owner, $runner->accounts['cash'], '2026-01-05');

    expect($keys())->not->toContain('missing_cash_count')
        ->and(CashCount::query()->sole()->business_closed)->toBeTrue()
        ->and(CashCount::query()->sole()->counted_amount)->toBeNull();
});

it('BR-CHK-04 a count also clears the alert; only the owner may dismiss', function () {
    $runner = at1DayOne();
    freezeToday('2026-01-06');

    expect(collect(Alerts::for($runner->accountant))->pluck('key')->all())->toContain('missing_cash_count');

    $this->actingAs($runner->accountant)->post(route('cash-counts.dismiss'), ['date' => '2026-01-05', 'money_account_id' => $runner->accounts['cash']->id])->assertForbidden();
    $this->actingAs($runner->accountant)->post(route('cash-counts.store'), ['date' => '2026-01-05', 'money_account_id' => $runner->accounts['cash']->id, 'counted_amount' => '18900'])
        ->assertRedirect(route('cash-counts.index'));

    expect(collect(Alerts::for($runner->owner))->pluck('key')->all())->not->toContain('missing_cash_count');
});

it('BR-CHK-01 page 28 shows the system amount and the history; banks are refused', function () {
    $runner = at1DayOne();
    app(CreateCashCount::class)->handle($runner->owner, $runner->accounts['cash'], '2026-01-05', '18900');

    $this->actingAs($runner->accountant)->getJson(route('cash-counts.system-amount', ['money_account_id' => $runner->accounts['cash']->id, 'date' => '2026-01-05']))
        ->assertExactJson(['system_amount' => '18900.00']);

    $this->actingAs($runner->accountant)->get(route('cash-counts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cash-counts/index')
            ->where('history.0.difference', '0.00')
            ->where('cash_accounts.0.name', 'Cash'));

    expect(fn () => app(CreateCashCount::class)->handle($runner->owner, $runner->accounts['hbl'], '2026-01-05', '1000'))
        ->toThrow(ValidationException::class, 'Select a cash account.');
});

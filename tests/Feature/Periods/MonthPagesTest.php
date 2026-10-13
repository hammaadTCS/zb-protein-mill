<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Checks\Actions\CreateCashCount;
use App\Domain\Periods\Actions\CloseMonth;
use App\Domain\Periods\Actions\RecordBankStatement;
use App\Domain\Periods\Actions\ReopenMonth;
use App\Domain\Periods\Models\PeriodEvent;
use App\Domain\Periods\Month;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/** January and February closed, with today in March. */
function closeJanuaryAndFebruary(): array
{
    [$owner, $accounts] = monthEndWorld();
    matchJanuary($owner, $accounts);
    app(CloseMonth::class)->handle($owner, '2026-01');

    freezeToday('2026-03-02');
    app(RecordBankStatement::class)->handle($owner, '2026-02', $accounts['hbl'], $accounts['hbl']->balance('2026-02-28'));
    app(CreateCashCount::class)->handle($owner, $accounts['cash'], '2026-02-28', $accounts['cash']->balance('2026-02-28'));
    app(CloseMonth::class)->handle($owner, '2026-02');

    return [$owner, $accounts];
}

it('BR-MEC-08 only the latest closed month can be reopened', function () {
    [$owner] = closeJanuaryAndFebruary();

    expect(fn () => app(ReopenMonth::class)->handle($owner, '2026-01', 'Wrong cash count'))
        ->toThrow(ValidationException::class, 'Only the latest closed month (February 2026) can be reopened.');

    app(ReopenMonth::class)->handle($owner, '2026-02', 'Bank charges missing');

    expect(Month::of('2026-02')->isClosed())->toBeFalse()
        ->and(Month::of('2026-01')->isClosed())->toBeTrue();

    app(ReopenMonth::class)->handle($owner, '2026-01', 'Wrong cash count');

    expect(Month::of('2026-01')->isClosed())->toBeFalse()
        ->and(fn () => app(ReopenMonth::class)->handle($owner, '2026-01', 'Again please'))->toThrow(ValidationException::class, 'No month is closed.');
});

it('BR-MEC-08 reopen requires a reason and logs it', function () {
    [$owner] = closeJanuaryAndFebruary();

    $this->actingAs($owner)->post(route('months.reopen', '2026-02'), ['reason' => 'abc'])
        ->assertSessionHasErrors(['reason' => 'Give a reason of at least 5 characters.']);

    expect(Month::of('2026-02')->isClosed())->toBeTrue();

    $this->actingAs($owner)->post(route('months.reopen', '2026-02'), ['reason' => 'Bank charges missing'])
        ->assertRedirect(route('months.index'));

    $period = Month::of('2026-02')->period();
    $event = PeriodEvent::query()->where('period_id', $period->id)->orderByDesc('id')->first();
    $audit = AuditLog::query()->where('action', 'month_reopened')->sole();

    expect($period->status)->toBe('open')
        ->and($period->closed_by)->toBeNull()
        ->and([$event->event, $event->reason, $event->user_id])->toBe(['reopened', 'Bank charges missing', $owner->id])
        ->and([$audit->auditable_id, $audit->reason, $audit->new_values])->toBe([$period->id, 'Bank charges missing', ['status' => 'open']])
        ->and($audit->old_values['status'])->toBe('closed');
});

it('BR-MEC-08 accountant cannot reopen or close', function () {
    closeJanuaryAndFebruary();
    $accountant = accountant();

    $this->actingAs($accountant)->post(route('months.reopen', '2026-02'), ['reason' => 'Bank charges missing'])->assertForbidden();
    $this->actingAs($accountant)->get(route('months.index'))->assertForbidden();
    $this->actingAs($accountant)->get(route('month-end.show'))->assertForbidden();
    $this->actingAs($accountant)->post(route('month-end.close'), ['period' => '2026-03'])->assertForbidden();

    expect(Month::of('2026-02')->isClosed())->toBeTrue();
});

it('page 30 walks January from blocked to closed through the page routes', function () {
    [$owner, $accounts] = monthEndWorld();
    $cash = $accounts['cash']->balance('2026-01-31');

    $this->actingAs($owner)->get(route('month-end.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('month-end/index')
            ->where('month.key', '2026-01')
            ->where('month.ended', true)
            ->where('checks.4.check', 'bank_match')
            ->where('checks.4.passed', false)
            ->where('checks.5.check', 'cash_match')
            ->where('checks.5.details.counted_amount', null));

    $this->actingAs($owner)->post(route('month-end.bank-statement'), ['period' => '2026-01', 'money_account_id' => $accounts['hbl']->id, 'statement_balance' => $accounts['hbl']->balance('2026-01-31')])
        ->assertRedirect(route('month-end.show'));
    $this->actingAs($owner)->post(route('month-end.cash-count'), ['period' => '2026-01', 'money_account_id' => $accounts['cash']->id, 'counted_amount' => Decimal::sub($cash, '500')])
        ->assertRedirect(route('month-end.show'));

    $this->actingAs($owner)->post(route('month-end.close'), ['period' => '2026-01'])
        ->assertRedirect(route('month-end.show'))
        ->assertSessionHasErrors('period');

    $this->actingAs($owner)->get(route('month-end.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('checks.4.passed', true)
            ->where('checks.5.passed', false)
            ->where('checks.5.details.difference', '-500.00'));

    $this->actingAs($owner)->post(route('month-end.cash-difference'), ['period' => '2026-01', 'money_account_id' => $accounts['cash']->id, 'amount' => '500.00', 'direction' => 'shortage', 'note' => 'Short at day end'])
        ->assertRedirect(route('month-end.show'))
        ->assertInertiaFlash('voucher', 'CSH-000001');

    $this->actingAs($owner)->post(route('month-end.close'), ['period' => '2026-01', 'accept_pending_rates' => false])
        ->assertRedirect(route('months.index'));

    expect(Month::of('2026-01')->isClosed())->toBeTrue();

    $this->actingAs($owner)->get(route('months.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('months/index')
            ->where('months.0.key', '2026-02')
            ->where('months.0.status', 'open')
            ->where('months.1.key', '2026-01')
            ->where('months.1.status', 'closed')
            ->where('months.1.closed_by', $owner->name)
            ->where('months.1.can_reopen', true)
            ->where('can_reopen', true));

    // February has not ended: the page says so and shows no checks.
    $this->actingAs($owner)->get(route('month-end.show'))
        ->assertInertia(fn (Assert $page) => $page->where('month.key', '2026-02')->where('month.ended', false)->where('checks', []));
});

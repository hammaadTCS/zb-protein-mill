<?php

use App\Domain\OpeningBalances\Actions\SetOpeningBalance;
use App\Domain\Periods\Actions\CloseMonth;
use App\Domain\Periods\Actions\ReopenMonth;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('BR-MEC-09 opening change after the first close is refused', function () {
    [$owner, $accounts] = monthEndWorld();
    $before = $accounts['cash']->balance();
    matchJanuary($owner, $accounts);
    app(CloseMonth::class)->handle($owner, '2026-01');

    expect(fn () => app(SetOpeningBalance::class)->handle($owner, ['money_account_id' => $accounts['cash']->id, 'amount' => '1.00']))
        ->toThrow(ValidationException::class, 'Opening balances are locked because January 2026 is closed.')
        ->and($accounts['cash']->balance())->toBe($before);

    $this->actingAs($owner)->post(route('opening-balances.store'), ['money_account_id' => $accounts['cash']->id, 'amount' => '1.00'])
        ->assertSessionHasErrors(['amount' => 'Opening balances are locked because January 2026 is closed.']);

    $this->actingAs($owner)->get(route('opening-balances.index'))
        ->assertInertia(fn (Assert $page) => $page->where('locked_by', 'January 2026'));
});

it('BR-MEC-09 openings can change again only when no month is closed', function () {
    [$owner, $accounts] = monthEndWorld();
    matchJanuary($owner, $accounts);
    app(CloseMonth::class)->handle($owner, '2026-01');
    app(ReopenMonth::class)->handle($owner, '2026-01', 'Opening of cash was wrong');

    app(SetOpeningBalance::class)->handle($owner, ['money_account_id' => $accounts['cash']->id, 'amount' => '1.00']);

    expect($accounts['cash']->balance('2026-01-01'))->toBe('1.00');

    $this->actingAs($owner)->get(route('opening-balances.index'))
        ->assertInertia(fn (Assert $page) => $page->where('locked_by', null));
});

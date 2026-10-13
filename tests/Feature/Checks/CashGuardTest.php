<?php

use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Payments\Actions\UpdatePayment;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Support\Warnings\WarningRequired;
use Illuminate\Support\Str;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * Cash holding 3000, a bank holding 3000, a supplier.
 *
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}}
 */
function cashWorld(): array
{
    $owner = owner();

    return [$owner, acceptanceWorld([
        'money_accounts' => [['key' => 'cash', 'name' => 'Cash', 'type' => 'cash'], ['key' => 'hbl', 'name' => 'HBL', 'type' => 'bank']],
        'parties' => [['key' => 'aslam', 'type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']],
        'opening_balances' => [['money_account' => 'cash', 'amount' => '3000.00'], ['money_account' => 'hbl', 'amount' => '3000.00']],
    ], $owner)];
}

/**
 * @param  list<string>  $confirmed
 */
function cashPayment(User $by, array $world, string $account, string $amount, array $confirmed = []): Payment
{
    return app(CreatePayment::class)->handle($by, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-10', 'direction' => 'paid',
        'party_id' => $world['parties']['aslam']->id, 'amount' => $amount,
        'money_account_id' => $world['accounts'][$account]->id, 'reference_no' => 'REF-1',
    ], $confirmed);
}

it('BR-GEN-10 paying 5000 from cash holding 3000 warns; confirmed it saves', function () {
    [$owner, $world] = cashWorld();

    expect(fn () => cashPayment($owner, $world, 'cash', '5000'))
        ->toThrow(WarningRequired::class, 'Cash will go below zero. An entry may be missing.');

    $saved = cashPayment($owner, $world, 'cash', '5000', ['negative_cash']);

    expect($saved->voucher_no)->toBe('PAY-000001')
        ->and($world['accounts']['cash']->balance())->toBe('-2000.00');
});

it('BR-GEN-10 paying exactly the cash held does not warn', function () {
    [$owner, $world] = cashWorld();

    expect(cashPayment($owner, $world, 'cash', '3000')->voucher_no)->toBe('PAY-000001');
});

it('BR-GEN-10 bank accounts never trigger it', function () {
    [$owner, $world] = cashWorld();

    expect(cashPayment($owner, $world, 'hbl', '5000')->voucher_no)->toBe('PAY-000001');
});

it('BR-GEN-10 editing a payment counts only the difference', function () {
    [$owner, $world] = cashWorld();
    $payment = cashPayment($owner, $world, 'cash', '2500'); // cash left: 500
    $form = fn (string $amount) => [
        'version' => $payment->fresh()?->updated_at?->toIso8601String(), 'date' => '2026-01-10', 'direction' => 'paid',
        'party_id' => $world['parties']['aslam']->id, 'amount' => $amount, 'money_account_id' => $world['accounts']['cash']->id,
    ];

    // 2500 → 3000 needs 500 more: exactly what is left, so no warning.
    $edited = app(UpdatePayment::class)->handle($owner, $payment, $form('3000'));
    expect($edited->amount)->toBe('3000.00');

    // 3000 → 3500 needs 500 more than is left.
    expect(fn () => app(UpdatePayment::class)->handle($owner, $edited, $form('3500')))
        ->toThrow(WarningRequired::class, 'Cash will go below zero.');
});

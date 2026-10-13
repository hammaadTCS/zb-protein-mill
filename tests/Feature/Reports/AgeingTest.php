<?php

use App\Domain\Alerts\Alerts;
use App\Domain\Items\Models\Item;
use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Reports\Ageing;
use App\Domain\Sales\Actions\CancelSale;
use App\Domain\Sales\Actions\CreateSale;
use App\Models\User;
use Illuminate\Support\Str;

/** T-12 data: today is 2026-02-05; the entries are saved on their own dates. */
function ageingSale(User $owner, Party $buyer, string $date, string $kg): mixed
{
    freezeToday($date);

    return app(CreateSale::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => $date, 'party_id' => $buyer->id,
        'item_id' => Item::query()->where('code', 'PROTEIN')->value('id'), 'kg' => $kg, 'rate' => '100',
    ]);
}

function ageingPayment(User $owner, Party $party, string $direction, string $date, string $amount, int $accountId): void
{
    freezeToday($date);
    app(CreatePayment::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => $date, 'direction' => $direction, 'party_id' => $party->id,
        'amount' => $amount, 'money_account_id' => $accountId, 'reference_no' => 'T12-'.$date,
    ]);
}

it('T-12 overdue and average days to pay for a buyer with 15-day terms', function () {
    $owner = owner();
    $bank = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'HBL', 'type' => 'bank', 'bank_name' => 'HBL']);
    $abc = app(CreateParty::class)->handle($owner, ['type' => 'buyer', 'name' => 'ABC Feeds', 'mobile' => '03000000004', 'payment_terms_days' => 15]);

    ageingSale($owner, $abc, '2026-01-01', '100'); // 10000.00
    ageingSale($owner, $abc, '2026-01-10', '200'); // 20000.00
    ageingPayment($owner, $abc, 'received', '2026-01-20', '15000', $bank->id);
    freezeToday('2026-02-05');

    expect(Ageing::overdueFor($abc->refresh(), '2026-02-05'))->toBe('15000.00')
        ->and(Ageing::averageDaysToPay($abc, '2026-01-01', '2026-02-05'))->toBe('19.00')
        ->and(collect(Alerts::for($owner))->firstWhere('key', 'overdue_buyers')['count'] ?? 0)->toBe(1);
});

it('T-12 BR-CHK-06 advance age of a supplier paid before delivery', function () {
    $owner = owner();
    $bank = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'HBL', 'type' => 'bank', 'bank_name' => 'HBL']);
    $bashir = app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => 'Bashir', 'mobile' => '03000000003']);

    ageingPayment($owner, $bashir, 'paid', '2026-01-02', '20000', $bank->id);
    freezeToday('2026-01-05');
    app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $bashir->id,
        'item_id' => Item::query()->where('code', 'WOOD')->value('id'), 'kg' => '1500', 'rate' => '10',
    ]);
    freezeToday('2026-02-05');

    expect(Ageing::advanceAgeDays($bashir, '2026-02-05'))->toBe(34)
        ->and(collect(Alerts::for($owner))->firstWhere('key', 'aged_advances')['count'] ?? 0)->toBe(1) // limit 30
        ->and(Ageing::advanceAgeDays($bashir, '2026-01-31'))->toBe(29);
});

it('T-12 a buyer with no terms has nothing overdue', function () {
    $owner = owner();
    $buyer = app(CreateParty::class)->handle($owner, ['type' => 'buyer', 'name' => 'Rehman Feeds', 'mobile' => '03000000005']);
    ageingSale($owner, $buyer, '2026-01-01', '100');
    freezeToday('2026-02-05');

    expect(Ageing::overdueFor($buyer->refresh(), '2026-02-05'))->toBe('0.00')
        ->and(Ageing::averageDaysToPay($buyer, '2026-01-01', '2026-02-05'))->toBeNull();
});

it('spec §12.6 a cancelled sale drops out of the matching', function () {
    $owner = owner();
    $abc = app(CreateParty::class)->handle($owner, ['type' => 'buyer', 'name' => 'ABC Feeds', 'mobile' => '03000000004', 'payment_terms_days' => 15]);
    $first = ageingSale($owner, $abc, '2026-01-01', '100');
    ageingSale($owner, $abc, '2026-01-10', '200');
    freezeToday('2026-01-11');
    app(CancelSale::class)->handle($owner, $first, 'Entered by mistake');
    freezeToday('2026-02-05');

    expect(Ageing::overdueFor($abc->refresh(), '2026-02-05'))->toBe('20000.00');
});

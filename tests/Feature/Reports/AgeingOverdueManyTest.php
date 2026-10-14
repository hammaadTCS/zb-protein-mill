<?php

use App\Domain\Items\Models\Item;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Reports\Ageing;
use App\Domain\Sales\Actions\CancelSale;
use App\Domain\Sales\Actions\CreateSale;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Support\AcceptanceRunner;

/**
 * The rule Ageing::overdueMany() runs in SQL, written out in PHP: walking back from the
 * newest increase, each one is open for what is left of the balance after every newer
 * increase, never below zero and never above its own amount.
 *
 * @param  list<array{source_type: string, date: string, net: string}>  $movements  in date order
 */
function newestFirstOverdue(array $movements, int $terms, string $asOf): string
{
    $left = Decimal::sum(array_column($movements, 'net'));
    $overdue = '0.00';

    for ($i = count($movements) - 1; $i >= 0 && Decimal::isPositive($left); $i--) {
        if (! Decimal::isPositive($movements[$i]['net'])) {
            continue;
        }

        $part = Decimal::compare($movements[$i]['net'], $left) <= 0 ? $movements[$i]['net'] : $left;
        $left = Decimal::sub($left, $part);

        if ($movements[$i]['source_type'] === 'sale' && CarbonImmutable::parse($movements[$i]['date'])->diffInDays(CarbonImmutable::parse($asOf)) > $terms) {
            $overdue = Decimal::add($overdue, $part);
        }
    }

    return $overdue;
}

/**
 * A random party history in date order, the shape Ageing reads from the ledger. Amounts
 * are built as strings (rupees and paisa), never as floats.
 *
 * @return list<array{source_type: string, date: string, net: string}>
 */
function randomMovements(int $count): array
{
    $date = CarbonImmutable::parse('2026-01-01');
    $types = ['opening_balance', 'sale', 'sale', 'sale', 'payment', 'receipt', 'receipt', 'adjustment'];
    $movements = [];

    for ($i = 0; $i < $count; $i++) {
        $date = $date->addDays(mt_rand(0, 20));
        $type = $types[mt_rand(0, count($types) - 1)];
        $increases = in_array($type, ['opening_balance', 'sale', 'payment'], true) ? mt_rand(0, 9) > 0 : mt_rand(0, 9) === 0;
        $amount = mt_rand(1, 99999).'.'.str_pad((string) mt_rand(0, 99), 2, '0', STR_PAD_LEFT);
        $movements[] = ['source_type' => $type, 'date' => $date->toDateString(), 'net' => ($increases ? '' : '-').$amount];
    }

    return $movements;
}

it('spec §12.6 newest-first open parts equal the oldest-first match on 3,000 random histories', function () {
    mt_srand(20261014); // reproducible
    $overdueCases = 0;

    for ($case = 0; $case < 3000; $case++) {
        $movements = randomMovements(mt_rand(0, 60));
        $last = $movements === [] ? '2026-01-01' : end($movements)['date'];
        $asOf = CarbonImmutable::parse($last)->addDays(mt_rand(0, 120))->toDateString();
        $terms = mt_rand(0, 60);

        $expected = Ageing::overdueFromMatch(Ageing::matchMovements($movements), $terms, $asOf);

        expect(newestFirstOverdue($movements, $terms, $asOf))->toBe($expected, "case {$case}");
        $overdueCases += $expected === '0.00' ? 0 : 1;
    }

    // The histories reach real overdue amounts, not only zeros.
    expect($overdueCases)->toBeGreaterThan(1000);
});

it('spec §12.6 overdueMany gives each buyer the same overdue amount as overdueFor', function () {
    $runner = AcceptanceRunner::load('at2')->setUp()->runAll();
    $buyers = Party::query()->where('type', PartyType::Buyer->value)->get();
    $buyers->each(fn (Party $buyer) => $buyer->forceFill(['payment_terms_days' => 0])->save());
    $buyers = $buyers->fresh();

    foreach (['2026-01-05', '2026-01-06', '2026-01-31', '2026-03-31'] as $asOf) {
        $many = Ageing::overdueMany($buyers, $asOf);

        foreach ($buyers as $buyer) {
            expect($many[$buyer->id])->toBe(Ageing::overdueFor($buyer, $asOf), "{$buyer->name} on {$asOf}");
        }
    }

    // At least one buyer is overdue, so the comparison is not only of zeros.
    expect(collect(Ageing::overdueMany($buyers, '2026-03-31'))->contains(fn (string $amount) => $amount !== '0.00'))->toBeTrue()
        ->and($runner->parties)->not->toBeEmpty();
});

it('spec §12.6 overdueMany gives 0.00 to a buyer without payment terms', function () {
    AcceptanceRunner::load('at2')->setUp()->runAll();
    $buyers = Party::query()->where('type', PartyType::Buyer->value)->get();
    $buyers->each(fn (Party $buyer) => $buyer->forceFill(['payment_terms_days' => null])->save());

    expect(array_values(array_unique(Ageing::overdueMany($buyers->fresh(), '2026-03-31'))))->toBe(['0.00']);
});

it('spec §12.6 overdueMany equals overdueFor on random sales, receipts and a cancellation', function () {
    mt_srand(1410);
    freezeToday('2026-03-31');
    $owner = owner();
    acceptanceWorld(acceptance('at1'), $owner);
    $protein = Item::query()->where('code', 'PROTEIN')->value('id');
    $cash = MoneyAccount::query()->where('type', 'cash')->value('id');
    $buyers = [];

    foreach ([7, 15, 30] as $i => $terms) {
        $buyer = app(CreateParty::class)->handle($owner, ['type' => 'buyer', 'name' => "Random Buyer {$i}", 'mobile' => '0311000000'.$i, 'payment_terms_days' => $terms]);
        $buyers[] = $buyer;
        $date = CarbonImmutable::parse('2026-01-01');

        for ($n = 0; $n < 20; $n++) {
            $date = $date->addDays(mt_rand(0, 4));

            if (mt_rand(0, 2) > 0) {
                $sale = app(CreateSale::class)->handle($owner, [
                    'idempotency_key' => (string) Str::uuid(), 'date' => $date->toDateString(), 'party_id' => $buyer->id,
                    'item_id' => $protein, 'kg' => (string) mt_rand(10, 500), 'rate' => (string) mt_rand(150, 250),
                ], ['duplicate_entry', 'unusual_rate', 'credit_limit']);

                if ($n === 5) {
                    app(CancelSale::class)->handle($owner, $sale, 'Entered twice by mistake');
                }
            } else {
                app(CreatePayment::class)->handle($owner, [
                    'idempotency_key' => (string) Str::uuid(), 'date' => $date->toDateString(), 'direction' => 'received',
                    'party_id' => $buyer->id, 'amount' => (string) mt_rand(1000, 60000), 'money_account_id' => $cash,
                ], ['overpayment_info', 'negative_cash']);
            }
        }
    }

    $fresh = Party::query()->whereIn('id', array_map(fn (Party $p) => $p->id, $buyers))->get();

    foreach (['2026-01-20', '2026-02-15', '2026-03-31'] as $asOf) {
        $many = Ageing::overdueMany($fresh, $asOf);

        foreach ($fresh as $buyer) {
            expect($many[$buyer->id])->toBe(Ageing::overdueFor($buyer, $asOf), "{$buyer->name} on {$asOf}");
        }
    }

    expect(collect(Ageing::overdueMany($fresh, '2026-03-31'))->contains(fn (string $amount) => $amount !== '0.00'))->toBeTrue();

    foreach ([['2026-01-01', '2026-01-31'], ['2026-02-01', '2026-03-31'], ['2026-01-01', '2026-03-31'], ['2026-03-20', '2026-03-31']] as [$from, $to]) {
        $many = Ageing::averageDaysToPayMany($fresh, $from, $to);

        foreach ($fresh as $buyer) {
            expect($many[$buyer->id])->toBe(Ageing::averageDaysToPay($buyer, $from, $to), "{$buyer->name} {$from} to {$to}");
        }
    }

    expect(collect(Ageing::averageDaysToPayMany($fresh, '2026-01-01', '2026-03-31'))->filter()->count())->toBeGreaterThan(0);
});

it('spec §12.6 averageDaysToPayMany equals averageDaysToPay after AT-2 with terms', function () {
    AcceptanceRunner::load('at2')->setUp()->runAll();
    $buyers = Party::query()->where('type', PartyType::Buyer->value)->get();

    foreach ([['2026-01-01', '2026-01-31'], ['2026-01-06', '2026-01-06'], ['2026-02-01', '2026-02-28']] as [$from, $to]) {
        $many = Ageing::averageDaysToPayMany($buyers, $from, $to);

        foreach ($buyers as $buyer) {
            expect($many[$buyer->id])->toBe(Ageing::averageDaysToPay($buyer, $from, $to), "{$buyer->name} {$from} to {$to}");
        }
    }
});

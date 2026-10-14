<?php

use App\Domain\Items\Models\Item;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Reports\Queries\KhataRow;
use App\Domain\Reports\Queries\PartyKhata;
use App\Domain\Reports\Queries\TrialBalance;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * AT-1 set-up and its first purchase entries, through the real Actions.
 *
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}}
 */
function at1WithEntries(int $count): array
{
    $owner = owner();
    $at1 = acceptance('at1');
    $world = acceptanceWorld($at1, $owner);

    foreach (array_slice($at1['entries'], 0, $count) as $entry) {
        app(CreatePurchase::class)->handle($owner, [
            'idempotency_key' => (string) Str::uuid(),
            'date' => $entry['date'],
            'party_id' => $world['parties'][$entry['party']]->id,
            'item_id' => Item::query()->where('name', $entry['item'])->value('id'),
            'kg' => $entry['kg'],
            'rate' => $entry['rate'],
            'paid_now' => ['amount' => $entry['paid_now']['amount'], 'money_account_id' => $world['accounts'][$entry['paid_now']['money_account']]->id],
        ]);
    }

    return [$owner, $world];
}

/** @param list<KhataRow> $rows */
function khataRows(array $rows): array
{
    return array_map(fn (KhataRow $r) => [$r->date, $r->voucher, $r->description, $r->increase, $r->decrease, $r->balance->words, $r->balance->amount], $rows);
}

it('R-01 khata after AT-1 entries 1 and 2 shows Karim "We owe 18800.00" with two rows', function () {
    [, $world] = at1WithEntries(2);

    $khata = PartyKhata::for($world['parties']['karim']);

    expect(khataRows($khata->rows))->toBe([
        ['2026-01-05', 'PUR-000002', 'Waste 600 kg @ 48', '28800.00', null, 'We owe', '28800.00'],
        ['2026-01-05', 'PAY-000002', 'Paid – Cash', null, '10000.00', 'We owe', '18800.00'],
    ])
        ->and([$khata->closing->words, $khata->closing->amount])->toBe(['We owe', '18800.00']);
});

it('R-01 opening row uses the balance before the from date', function () {
    [$owner, $world] = at1WithEntries(2);
    $karim = $world['parties']['karim'];
    app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-20', 'party_id' => $karim->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '100', 'rate' => '40',
    ]);

    $khata = PartyKhata::for($karim, '2026-01-10', '2026-01-31');

    expect([$khata->opening->words, $khata->opening->amount])->toBe(['We owe', '18800.00'])
        ->and(khataRows($khata->rows))->toBe([
            ['2026-01-20', 'PUR-000003', 'Waste 100 kg @ 40', '4000.00', null, 'We owe', '22800.00'],
        ]);
});

it('R-01 a partner khata shows the opening capital', function () {
    [, $world] = at1WithEntries(0);

    $khata = PartyKhata::for($world['parties']['ali']);

    expect(khataRows($khata->rows))->toBe([
        ['2026-01-01', 'OPB-000003', 'Opening balance', '200000.00', null, 'Net invested', '200000.00'],
    ]);
});

it('page 19 shows the party with its balance and khata', function () {
    [, $world] = at1WithEntries(2);

    $this->actingAs(accountant())->get(route('parties.show', $world['parties']['karim']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('parties/show')
            ->where('party.code', 'S-0002')
            ->where('party.balance.words', 'We owe')
            ->where('party.balance.amount', '18800.00')
            ->has('khata.rows', 2)
            ->where('khata.rows.0.route', route('purchases.show', Purchase::query()->where('voucher_no', 'PUR-000002')->value('public_id'), false)));
});

it('R-07 page totals equal TrialBalance query totals', function () {
    at1WithEntries(2);
    $query = TrialBalance::asOf('2026-01-31', true);

    $this->actingAs(owner())->get(route('reports.trial-balance', ['as_of' => '2026-01-31', 'parties' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/trial-balance')
            ->where('total_debit', $query->totalDebit)
            ->where('total_credit', $query->totalCredit)
            ->where('balanced', true)
            ->has('rows', count($query->rows)));

    expect($query->balanced)->toBeTrue()
        ->and(collect($query->rows)->firstWhere('partyCode', 'S-0002')?->balanceCredit)->toBe('18800.00')
        ->and(collect($query->rows)->firstWhere('partyCode', 'S-0002')?->partyName)->toBe('Karim');
});

it('R-07 the trial balance needs report.view', function () {
    $this->actingAs(User::factory()->create())->get(route('reports.trial-balance'))->assertForbidden();
});

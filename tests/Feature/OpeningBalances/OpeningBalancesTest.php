<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\OpeningBalances\Actions\MarkOpeningBalancesComplete;
use App\Domain\OpeningBalances\Actions\SetOpeningBalance;
use App\Domain\OpeningBalances\Models\OpeningBalance;
use App\Domain\OpeningBalances\OpeningBalances;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Models\Party;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-15'));

function openingParty(User $by, string $type, string $name, string $mobile): Party
{
    return app(CreateParty::class)->handle($by, ['type' => $type, 'name' => $name, 'mobile' => $mobile]);
}

/**
 * @param  array<string, mixed>  $data
 */
function setOpening(User $by, array $data): ?OpeningBalance
{
    return app(SetOpeningBalance::class)->handle($by, $data);
}

it('PR-17 cash opening 80000: Dr cash account / Cr 3900', function () {
    $owner = owner();
    $cash = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Main', 'type' => 'cash']);

    $opening = setOpening($owner, ['money_account_id' => $cash->id, 'amount' => '80000']);

    expect($opening?->voucher_no)->toBe('OPB-000001')
        ->and($opening?->as_of_date)->toBe('2026-01-01')
        ->and(journalLines($opening?->journal_entry_id))->toBe([
            ['1010', '80000.00', '0.00', null, null],
            ['3900', '0.00', '80000.00', null, null],
        ]);
});

it('PR-22 partner capital 200000: Dr 3900 / Cr 3000 with partner', function () {
    $owner = owner();
    $ali = openingParty($owner, 'partner', 'Ali', '03000000006');

    $opening = setOpening($owner, ['party_id' => $ali->id, 'side' => 'capital', 'amount' => '200000.00']);

    expect(journalLines($opening?->journal_entry_id))->toBe([
        ['3900', '200000.00', '0.00', null, null],
        ['3000', '0.00', '200000.00', 'P-0001', null],
    ]);
});

it('PR-18 we owe a supplier: Dr 3900 / Cr 2000 with supplier', function () {
    $owner = owner();
    $aslam = openingParty($owner, 'supplier', 'Aslam', '03000000001');

    $opening = setOpening($owner, ['party_id' => $aslam->id, 'side' => 'we_owe', 'amount' => '15000']);

    expect(journalLines($opening?->journal_entry_id))->toBe([
        ['3900', '15000.00', '0.00', null, null],
        ['2000', '0.00', '15000.00', 'S-0001', null],
    ])->and(partyBalance($aslam))->toBe(['words' => 'We owe', 'amount' => '15000.00']);
});

it('PR-19 advance with an other party: Dr 2100 with party / Cr 3900', function () {
    $owner = owner();
    $shop = openingParty($owner, 'other', 'Test Transporter', '03000000009');

    $opening = setOpening($owner, ['party_id' => $shop->id, 'side' => 'they_owe', 'amount' => '2500.50']);

    expect(journalLines($opening?->journal_entry_id))->toBe([
        ['2100', '2500.50', '0.00', 'O-0001', null],
        ['3900', '0.00', '2500.50', null, null],
    ])->and(partyBalance($shop))->toBe(['words' => 'Advance', 'amount' => '2500.50']);
});

it('PR-20 a buyer owes us: Dr 1200 with buyer / Cr 3900', function () {
    $owner = owner();
    $abc = openingParty($owner, 'buyer', 'ABC Feeds', '03000000004');

    $opening = setOpening($owner, ['party_id' => $abc->id, 'side' => 'they_owe', 'amount' => '61000']);

    expect(journalLines($opening?->journal_entry_id))->toBe([
        ['1200', '61000.00', '0.00', 'B-0001', null],
        ['3900', '0.00', '61000.00', null, null],
    ])->and(partyBalance($abc))->toBe(['words' => 'Owes us', 'amount' => '61000.00']);
});

it('PR-21 we owe an employee: Dr 3900 / Cr 1300 with employee', function () {
    $owner = owner();
    $nadeem = openingParty($owner, 'employee', 'Nadeem', '03000000010');

    $opening = setOpening($owner, ['party_id' => $nadeem->id, 'side' => 'we_owe', 'amount' => '7000']);

    expect(journalLines($opening?->journal_entry_id))->toBe([
        ['3900', '7000.00', '0.00', null, null],
        ['1300', '0.00', '7000.00', 'E-0001', null],
    ])->and(partyBalance($nadeem))->toBe(['words' => 'We owe', 'amount' => '7000.00']);
});

it('AT-1 openings leave Opening Balance Equity at 0.00', function () {
    $owner = owner();
    $at1 = acceptance('at1');
    $accounts = [];
    $parties = [];

    foreach ($at1['money_accounts'] as $account) {
        $accounts[$account['key']] = app(CreateMoneyAccount::class)->handle($owner, $account);
    }

    foreach ($at1['parties'] as $party) {
        $parties[$party['key']] = openingParty($owner, $party['type'], $party['name'], $party['mobile']);
    }

    foreach ($at1['opening_balances'] as $opening) {
        setOpening($owner, isset($opening['money_account'])
            ? ['money_account_id' => $accounts[$opening['money_account']]->id, 'amount' => $opening['amount']]
            : ['party_id' => $parties[$opening['party']]->id, 'side' => $opening['side'], 'amount' => $opening['amount']]);
    }

    expect(OpeningBalances::equity())->toBe('0.00')
        ->and(ledgerBalance('3900'))->toBe('0.00');
});

it('BR-OPB-03 replacing an opening reverses the old journal', function () {
    $owner = owner();
    $cash = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Main', 'type' => 'cash']);
    $first = setOpening($owner, ['money_account_id' => $cash->id, 'amount' => '80000']);
    $oldJournal = $first?->journal_entry_id;

    $second = setOpening($owner, ['money_account_id' => $cash->id, 'amount' => '75000']);
    $reversal = DB::table('journal_entries')->where('reversal_of_id', $oldJournal)->first();

    expect($second?->id)->toBe($first?->id)
        ->and($second?->voucher_no)->toBe('OPB-000001')
        ->and($reversal?->posting_date)->toBe('2026-01-01')
        ->and(journalLines($reversal?->id))->toBe([
            ['1010', '0.00', '80000.00', null, null],
            ['3900', '80000.00', '0.00', null, null],
        ])
        ->and(journalLines($second?->journal_entry_id))->toBe([
            ['1010', '75000.00', '0.00', null, null],
            ['3900', '0.00', '75000.00', null, null],
        ])
        ->and($cash->balance())->toBe('75000.00');
});

it('BR-OPB-03 amount 0 removes an opening', function () {
    $owner = owner();
    $cash = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Main', 'type' => 'cash']);
    setOpening($owner, ['money_account_id' => $cash->id, 'amount' => '80000']);

    $removed = setOpening($owner, ['money_account_id' => $cash->id, 'amount' => '0']);

    expect($removed?->amount)->toBe('0.00')
        ->and($removed?->journal_entry_id)->toBeNull()
        ->and($cash->balance())->toBe('0.00')
        ->and(setOpening($owner, ['money_account_id' => app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Spare', 'type' => 'cash'])->id, 'amount' => '0']))->toBeNull();
});

it('BR-OPB-02 a party side must fit its type', function () {
    $owner = owner();
    $ali = openingParty($owner, 'partner', 'Ali', '03000000006');

    expect(fn () => setOpening($owner, ['party_id' => $ali->id, 'side' => 'we_owe', 'amount' => '100']))
        ->toThrow(ValidationException::class, 'Select a side for this party.');
});

it('BR-OPB-04 mark complete refuses a wrong confirmed amount', function () {
    $owner = owner();
    $cash = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Main', 'type' => 'cash']);
    setOpening($owner, ['money_account_id' => $cash->id, 'amount' => '80000']);

    expect(fn () => app(MarkOpeningBalancesComplete::class)->handle($owner, '0.00'))
        ->toThrow(ValidationException::class, 'Opening Balance Equity is Rs 80,000.00. Confirm that figure.');

    expect(OpeningBalances::completed())->toBeFalse();

    app(MarkOpeningBalancesComplete::class)->handle($owner, '80000.00');

    expect(OpeningBalances::completed())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'opening_completed')->sole()->new_values)->toBe(['opening_balance_equity' => '80000.00']);
});

it('BR-OPB-03 openings are refused after they are marked complete', function () {
    $owner = owner();
    $cash = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Main', 'type' => 'cash']);
    app(MarkOpeningBalancesComplete::class)->handle($owner, '0.00');

    expect(fn () => setOpening($owner, ['money_account_id' => $cash->id, 'amount' => '100']))
        ->toThrow(ValidationException::class, 'Opening balances are marked complete and cannot change.');
});

it('BR-GEN-07 openings are refused while a required setting is empty', function () {
    $owner = owner();
    $cash = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Main', 'type' => 'cash']);
    DB::table('settings')->where('key', 'kg_decimals')->update(['value' => null]);

    expect(fn () => setOpening($owner, ['money_account_id' => $cash->id, 'amount' => '100']))
        ->toThrow(ValidationException::class, Settings::SETUP_INCOMPLETE);
});

it('BR-OPB-01 page 47 is for opening.manage only and saves a row', function () {
    $owner = owner();
    $cash = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Main', 'type' => 'cash']);

    $this->actingAs(accountant())->get(route('opening-balances.index'))->assertForbidden();

    $this->actingAs($owner)
        ->post(route('opening-balances.store'), ['money_account_id' => $cash->id, 'amount' => '80000'])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)->get(route('opening-balances.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('opening-balances/index')
            ->where('equity', '80000.00')
            ->where('money_accounts.0.opening.amount', '80000.00'));
});

<?php

use App\Domain\Items\Actions\CreateItem;
use App\Domain\Items\Models\Item;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\MoneyAccounts\Actions\DeactivateMoneyAccount;
use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $data
 */
function buy(User $by, array $data): Purchase
{
    return app(CreatePurchase::class)->handle($by, ['idempotency_key' => (string) Str::uuid(), ...$data]);
}

/**
 * An AT-1 purchase entry turned into form data.
 *
 * @param  array<string, mixed>  $entry
 * @param  array{accounts: array<string, mixed>, parties: array<string, Party>}  $world
 * @return array<string, mixed>
 */
function purchaseData(array $entry, array $world): array
{
    $data = [
        'date' => $entry['date'],
        'party_id' => $world['parties'][$entry['party']]->id,
        'item_id' => Item::query()->where('name', $entry['item'])->value('id'),
        'kg' => $entry['kg'],
        'rate' => $entry['rate'],
    ];

    if (isset($entry['paid_now'])) {
        $data['paid_now'] = [
            'amount' => $entry['paid_now']['amount'],
            'money_account_id' => $world['accounts'][$entry['paid_now']['money_account']]->id,
        ];
    }

    return $data;
}

/** A small world for rule tests: cash with an opening, a supplier, a buyer. */
function purchaseWorld(): array
{
    $owner = owner();
    $world = acceptanceWorld([
        'money_accounts' => [['key' => 'cash', 'name' => 'Cash', 'type' => 'cash']],
        'parties' => [
            ['key' => 'aslam', 'type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001'],
            ['key' => 'abc', 'type' => 'buyer', 'name' => 'ABC Feeds', 'mobile' => '03000000004'],
            ['key' => 'nadeem', 'type' => 'employee', 'name' => 'Nadeem', 'mobile' => '03000000010'],
        ],
    ], $owner);

    return [$owner, $world['accounts']['cash'], $world['parties']];
}

beforeEach(fn () => freezeToday('2026-01-31'));

it('AT-1 #1 Aslam 800 kg @ 50 paid now 40000 cash', function () {
    $owner = owner();
    $at1 = acceptance('at1');
    $world = acceptanceWorld($at1, $owner);

    $purchase = buy($owner, purchaseData($at1['entries'][0], $world));
    $payment = $purchase->payments()->sole();

    expect($purchase->voucher_no)->toBe('PUR-000001')
        ->and($payment->voucher_no)->toBe('PAY-000001')
        ->and($purchase->amount)->toBe('40000.00')
        ->and(journalLines($purchase->journal_entry_id))->toBe([
            ['5000', '40000.00', '0.00', null, 'Waste'],
            ['2000', '0.00', '40000.00', 'S-0001', null],
        ])
        ->and(journalLines($payment->journal_entry_id))->toBe([
            ['2000', '40000.00', '0.00', 'S-0001', null],
            ['1010', '0.00', '40000.00', null, null],
        ])
        ->and(partyBalance($world['parties']['aslam']))->toBe(['words' => 'Settled', 'amount' => '0.00'])
        ->and($world['accounts']['cash']->balance())->toBe('40000.00')
        ->and($payment->approval_status->value)->toBe('not_required')
        ->and($payment->purchase_id)->toBe($purchase->id);
});

it('AT-1 #2 Karim paid now 10000', function () {
    $owner = owner();
    $at1 = acceptance('at1');
    $world = acceptanceWorld($at1, $owner);

    buy($owner, purchaseData($at1['entries'][0], $world));
    $karim = buy($owner, purchaseData($at1['entries'][1], $world));

    expect($karim->voucher_no)->toBe('PUR-000002')
        ->and($karim->payments()->sole()->voucher_no)->toBe('PAY-000002')
        ->and(partyBalance($world['parties']['karim']))->toBe(['words' => 'We owe', 'amount' => '18800.00']);
});

it('BR-PUR-01 a buyer as supplier is refused', function () {
    [$owner, , $parties] = purchaseWorld();

    expect(fn () => buy($owner, ['date' => '2026-01-05', 'party_id' => $parties['abc']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '10', 'rate' => '50']))
        ->toThrow(ValidationException::class, 'Select an active supplier.');
});

it('BR-PUR-01 a product item is refused', function () {
    [$owner, , $parties] = purchaseWorld();

    expect(fn () => buy($owner, ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'PROTEIN')->value('id'), 'kg' => '10', 'rate' => '50']))
        ->toThrow(ValidationException::class, 'Select an active raw material or fuel item.');
});

it('BR-PUR-02 kg 0 is refused; kg with 4 decimals is refused', function () {
    [$owner, , $parties] = purchaseWorld();
    $data = ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'rate' => '50'];

    expect(fn () => buy($owner, [...$data, 'kg' => '0']))->toThrow(ValidationException::class, 'Kg must be greater than 0.')
        ->and(fn () => buy($owner, [...$data, 'kg' => '10.1234']))->toThrow(ValidationException::class, 'Kg can have at most 3 decimals.')
        ->and(buy($owner, [...$data, 'kg' => '812.500'])->kg)->toBe('812.500');
});

it('BR-PUR-03 an empty rate is "Rate pending" (from T3.1); a rate has at most rate_decimals decimals', function () {
    [$owner, , $parties] = purchaseWorld();
    $data = ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '100'];

    expect(buy($owner, $data)->rate_status->value)->toBe('pending')
        ->and(fn () => buy($owner, [...$data, 'rate' => '52.555']))->toThrow(ValidationException::class, 'Rate can have at most 2 decimals.')
        ->and(fn () => buy($owner, [...$data, 'rate' => '0']))->toThrow(ValidationException::class, 'Rate must be greater than 0.');
});

it('BR-PUR-03 amount is kg × rate rounded half up to paisa', function () {
    [$owner, , $parties] = purchaseWorld();

    $purchase = buy($owner, ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '812.5', 'rate' => '52.5']);

    expect($purchase->amount)->toBe('42656.25')
        ->and($purchase->rate)->toBe('52.5000')
        ->and($purchase->rate_status->value)->toBe('fixed');
});

it('BR-GEN-06 browser amount is ignored', function () {
    [$owner, , $parties] = purchaseWorld();

    $this->actingAs($owner)->post(route('purchases.store'), [
        'date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'),
        'kg' => '800', 'rate' => '50', 'amount' => '1', 'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors()->assertInertiaFlash('success', 'Saved. PUR-000001');

    expect(Purchase::query()->sole()->amount)->toBe('40000.00');
});

it('BR-GEN-05 a failed save consumes no voucher number', function () {
    [$owner, , $parties] = purchaseWorld();
    $feathers = app(CreateItem::class)->handle($owner, ['code' => 'FEATH', 'name' => 'Feathers', 'type' => 'raw_material']);
    LedgerAccount::query()->whereKey($feathers->purchase_ledger_account_id)->update(['is_active' => false]);
    $data = ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => $feathers->id, 'kg' => '10', 'rate' => '5'];

    expect(fn () => buy($owner, $data))->toThrow(InvalidArgumentException::class, 'is inactive');
    expect(Purchase::query()->count())->toBe(0);

    LedgerAccount::query()->whereKey($feathers->purchase_ledger_account_id)->update(['is_active' => true]);

    expect(buy($owner, $data)->voucher_no)->toBe('PUR-000001');
});

it('BR-GEN-07 purchase is refused while a required setting is empty', function () {
    [$owner, , $parties] = purchaseWorld();
    DB::table('settings')->where('key', 'unusual_rate_percent')->update(['value' => null]);

    expect(fn () => buy($owner, ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '10', 'rate' => '50']))
        ->toThrow(ValidationException::class, Settings::SETUP_INCOMPLETE);
});

it('BR-GEN-02 a future date or a date before the start date is refused', function () {
    [$owner, , $parties] = purchaseWorld();
    $data = ['party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '10', 'rate' => '50'];

    expect(fn () => buy($owner, [...$data, 'date' => '2026-02-01']))->toThrow(ValidationException::class, 'Date cannot be in the future.')
        ->and(fn () => buy($owner, [...$data, 'date' => '2025-12-31']))->toThrow(ValidationException::class, 'Date cannot be before the system start date');
});

it('BR-PUR-10 paid now above the total leaves an advance', function () {
    [$owner, $cash, $parties] = purchaseWorld();

    buy($owner, [
        'date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'),
        'kg' => '10', 'rate' => '100', 'paid_now' => ['amount' => '1500', 'money_account_id' => $cash->id],
    ]);

    expect(partyBalance($parties['aslam']))->toBe(['words' => 'Advance', 'amount' => '500.00']);
});

it('BR-PUR-10 paid now needs an active account and an employee as handled by', function () {
    [$owner, $cash, $parties] = purchaseWorld();
    $data = ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '10', 'rate' => '100'];

    expect(fn () => buy($owner, [...$data, 'paid_now' => ['amount' => '100', 'money_account_id' => $cash->id, 'handled_by_party_id' => $parties['abc']->id]]))
        ->toThrow(ValidationException::class, 'Handled by must be an active employee.');

    app(DeactivateMoneyAccount::class)->handle($owner, $cash);

    expect(fn () => buy($owner, [...$data, 'paid_now' => ['amount' => '100', 'money_account_id' => $cash->id]]))
        ->toThrow(ValidationException::class, 'Select an active account.');
    expect(Purchase::query()->count())->toBe(0);
});

it('T-18 BR-GEN-11 posting the same idempotency key twice creates one purchase, one payment, one voucher of each', function () {
    [$owner, $cash, $parties] = purchaseWorld();
    $form = [
        'date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'),
        'kg' => '800', 'rate' => '50', 'idempotency_key' => (string) Str::uuid(),
        'paid_now' => ['amount' => '40000', 'money_account_id' => $cash->id],
    ];

    $this->actingAs($owner)->post(route('purchases.store'), $form)->assertInertiaFlash('success', 'Saved. PUR-000001');
    $this->actingAs($owner)->post(route('purchases.store'), $form)->assertInertiaFlash('success', 'Already saved. PUR-000001');

    expect(Purchase::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and(DB::table('voucher_sequences')->whereIn('doc_type', ['purchase', 'payment_paid'])->orderBy('doc_type')->pluck('next_number', 'doc_type')->all())
        ->toBe(['payment_paid' => 2, 'purchase' => 2]);
});

it('accountant may create purchases; a user without purchase.create may not', function () {
    [, , $parties] = purchaseWorld();
    $form = ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '10', 'rate' => '50', 'idempotency_key' => (string) Str::uuid()];

    $this->actingAs(User::factory()->create())->post(route('purchases.store'), $form)->assertForbidden();
    $this->actingAs(accountant())->post(route('purchases.store'), $form)->assertSessionHasNoErrors();

    expect(Purchase::query()->count())->toBe(1);
});

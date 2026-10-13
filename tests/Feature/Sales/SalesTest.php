<?php

use App\Domain\Items\Models\Item;
use App\Domain\Parties\Actions\UpdateParty;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Models\RateChange;
use App\Domain\Sales\Actions\AddSaleRate;
use App\Domain\Sales\Actions\CancelSale;
use App\Domain\Sales\Actions\CreateSale;
use App\Domain\Sales\Actions\UpdateSale;
use App\Domain\Sales\Models\Sale;
use App\Models\User;
use App\Support\Warnings\WarningRequired;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}}
 */
function salesWorld(): array
{
    $owner = owner();

    return [$owner, acceptanceWorld(acceptance('at1'), $owner)];
}

/**
 * An AT-1 sale entry as form data.
 *
 * @param  array<string, mixed>  $entry
 * @return array<string, mixed>
 */
function saleData(array $entry, array $world): array
{
    $data = [
        'idempotency_key' => (string) Str::uuid(),
        'date' => $entry['date'],
        'party_id' => $world['parties'][$entry['party']]->id,
        'item_id' => Item::query()->where('name', $entry['item'])->value('id'),
        'kg' => $entry['kg'],
        'rate' => $entry['rate'],
    ];

    if (isset($entry['received_now'])) {
        $data['received_now'] = [
            'amount' => $entry['received_now']['amount'],
            'money_account_id' => $world['accounts'][$entry['received_now']['money_account']]->id,
            'reference_no' => $entry['received_now']['reference_no'] ?? null,
        ];
    }

    return $data;
}

/**
 * @param  array<string, mixed>  $data
 * @param  list<string>  $confirmed
 */
function sell(User $by, array $data, array $confirmed = []): Sale
{
    return app(CreateSale::class)->handle($by, ['idempotency_key' => (string) Str::uuid(), ...$data], $confirmed);
}

function at1Sale(int $no): array
{
    return collect(acceptance('at1')['entries'])->firstWhere('no', $no);
}

it('AT-1 #5 ABC 300 kg @ 210', function () {
    [$owner, $world] = salesWorld();

    $sale = sell($owner, saleData(at1Sale(5), $world));

    expect($sale->voucher_no)->toBe('SAL-000001')
        ->and(journalLines($sale->journal_entry_id))->toBe([
            ['1200', '63000.00', '0.00', 'B-0001', null],
            ['4000', '0.00', '63000.00', null, 'Protein product'],
        ])
        ->and(partyBalance($world['parties']['abc']))->toBe(['words' => 'Owes us', 'amount' => '63000.00']);
});

it('AT-1 #6 Rehman 200 kg @ 205 received now 41000 HBL', function () {
    [$owner, $world] = salesWorld();

    $sale = sell($owner, saleData(at1Sale(6), $world));
    $receipt = $sale->payments()->sole();

    expect($receipt->voucher_no)->toBe('RCV-000001')
        ->and($receipt->direction->value)->toBe('received')
        ->and($receipt->reference_no)->toBe('AT1-REF-06')
        ->and(journalLines($receipt->journal_entry_id))->toBe([
            ['1100', '41000.00', '0.00', null, null],
            ['1200', '0.00', '41000.00', 'B-0002', null],
        ])
        ->and(partyBalance($world['parties']['rehman']))->toBe(['words' => 'Settled', 'amount' => '0.00']);
});

it('BR-SAL-01 a supplier as buyer is refused', function () {
    [$owner, $world] = salesWorld();

    expect(fn () => sell($owner, ['date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id, 'item_id' => Item::query()->where('code', 'PROTEIN')->value('id'), 'kg' => '10', 'rate' => '200']))
        ->toThrow(ValidationException::class, 'Select an active buyer.');
});

it('BR-SAL-01 Waste as sale item is refused', function () {
    [$owner, $world] = salesWorld();

    expect(fn () => sell($owner, ['date' => '2026-01-05', 'party_id' => $world['parties']['abc']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '10', 'rate' => '200']))
        ->toThrow(ValidationException::class, 'Select an active product.');
});

it('BR-SAL-06 credit limit warning', function () {
    [$owner, $world] = salesWorld();
    $abc = $world['parties']['abc'];
    app(UpdateParty::class)->handle($owner, $abc, ['type' => 'buyer', 'name' => $abc->name, 'mobile' => $abc->mobile, 'area' => $abc->area, 'credit_limit' => '50000']);
    $data = saleData(at1Sale(5), $world);

    expect(fn () => sell($owner, $data))
        ->toThrow(WarningRequired::class, 'After this sale ABC Feeds will owe Rs 63,000.00, above the credit limit of Rs 50,000.00.');

    expect(sell($owner, $data, ['credit_limit'])->voucher_no)->toBe('SAL-000001');
});

it('BR-SAL-07 received now never needs approval even for the accountant', function () {
    [, $world] = salesWorld();

    $sale = sell(accountant(), saleData(at1Sale(6), $world));

    expect($sale->payments()->sole()->approval_status->value)->toBe('not_required')
        ->and($sale->payments()->sole()->journal_entry_id)->not->toBeNull();
});

it('T-05 for sales: a rate-pending sale posts nothing; adding the rate posts PR-04', function () {
    [$owner, $world] = salesWorld();
    $data = [...saleData(at1Sale(5), $world), 'rate' => null];
    $journals = DB::table('journal_entries')->count();

    $sale = sell($owner, $data);

    expect($sale->journal_entry_id)->toBeNull()
        ->and(DB::table('journal_entries')->count())->toBe($journals)
        ->and($world['parties']['abc']->balance()->pendingKg)->toBe('300.000');

    $sale = app(AddSaleRate::class)->handle($owner, $sale, '210', '2026-01-06');

    expect(journalLines($sale->journal_entry_id))->toBe([
        ['1200', '63000.00', '0.00', 'B-0001', null],
        ['4000', '0.00', '63000.00', null, 'Protein product'],
    ])
        ->and(RateChange::query()->sole()->target_type)->toBe('sale');
});

it('T-06 for sales: editing reverses and reposts; cancelling reverses and keeps the row', function () {
    [$owner, $world] = salesWorld();
    $sale = sell($owner, saleData(at1Sale(5), $world));
    $original = $sale->journal_entry_id;

    $edited = app(UpdateSale::class)->handle($owner, $sale, [
        'version' => $sale->fresh()?->updated_at?->toIso8601String(),
        'date' => $sale->entry_date, 'party_id' => $sale->party_id, 'item_id' => $sale->item_id, 'kg' => '310', 'rate' => $sale->rate,
    ]);

    expect(DB::table('journal_entries')->where('reversal_of_id', $original)->exists())->toBeTrue()
        ->and(journalLines($edited->journal_entry_id))->toBe([
            ['1200', '65100.00', '0.00', 'B-0001', null],
            ['4000', '0.00', '65100.00', null, 'Protein product'],
        ]);

    app(CancelSale::class)->handle($owner, $edited, 'Buyer did not take it');

    expect($edited->refresh()->status->value)->toBe('cancelled')
        ->and(Sale::query()->count())->toBe(1)
        ->and(partyBalance($world['parties']['abc']))->toBe(['words' => 'Settled', 'amount' => '0.00']);
});

it('BR-PUR-12 for sales: cancelling without the receipt leaves an advance', function () {
    [$owner, $world] = salesWorld();
    $sale = sell($owner, saleData(at1Sale(6), $world));

    app(CancelSale::class)->handle($owner, $sale, 'Wrong buyer chosen', false);

    expect(partyBalance($world['parties']['rehman']))->toBe(['words' => 'Advance', 'amount' => '41000.00'])
        ->and(Payment::query()->sole()->status->value)->toBe('active');
});

it('T-18 BR-GEN-11 the same idempotency key twice creates one sale and one voucher', function () {
    [$owner, $world] = salesWorld();
    $form = saleData(at1Sale(6), $world);

    $this->actingAs($owner)->post(route('sales.store'), $form)->assertInertiaFlash('success', 'Saved. SAL-000001');
    $this->actingAs($owner)->post(route('sales.store'), $form)->assertInertiaFlash('success', 'Already saved. SAL-000001');

    expect(Sale::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and(DB::table('voucher_sequences')->whereIn('doc_type', ['sale', 'payment_received'])->orderBy('doc_type')->pluck('next_number', 'doc_type')->all())
        ->toBe(['payment_received' => 2, 'sale' => 2]);
});

it('pages 6, 7 and 8 and the rate-pending list show sales', function () {
    [$owner, $world] = salesWorld();
    $sale = sell($owner, saleData(at1Sale(5), $world));
    $pending = sell($owner, [...saleData(at1Sale(6), $world), 'rate' => null, 'received_now' => null]);

    $this->actingAs($owner)->get(route('sales.index'))
        ->assertInertia(fn (Assert $page) => $page->component('sales/index')->where('sales.total', 2)->where('totals.kg', '500.000')->where('totals.amount', '63000.00'));
    $this->actingAs($owner)->get(route('sales.create'))
        ->assertInertia(fn (Assert $page) => $page->component('sales/create')->has('items', 2)->where('items.0.name', 'Protein product'));
    $this->actingAs($owner)->get(route('sales.show', $sale))
        ->assertInertia(fn (Assert $page) => $page->component('sales/show')->where('sale.voucher_no', 'SAL-000001'));
    $this->actingAs($owner)->get(route('rate-pending.index', ['type' => 'sale']))
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.voucher_no', $pending->voucher_no)->where('rows.0.type', 'sale'));
});

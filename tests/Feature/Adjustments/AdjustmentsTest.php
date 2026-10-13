<?php

use App\Domain\Adjustments\Actions\CancelAdjustment;
use App\Domain\Adjustments\Actions\CreateAdjustment;
use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Items\Models\Item;
use App\Domain\Purchases\Actions\CancelPurchase;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Actions\CancelSale;
use App\Domain\Sales\Actions\CreateSale;
use App\Domain\Sales\Actions\UpdateSale;
use App\Domain\Sales\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * The AT-2 world with sale s5 (#5) saved.
 *
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}, Sale}
 */
function adjustmentWorld(): array
{
    $owner = owner();
    $world = acceptanceWorld(acceptance('at2'), $owner);
    $s5 = collect(acceptance('at2')['entries'])->firstWhere('no', 5);
    $sale = app(CreateSale::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => $s5['date'], 'party_id' => $world['parties'][$s5['party']]->id,
        'item_id' => Item::query()->where('name', $s5['item'])->value('id'), 'kg' => $s5['kg'], 'rate' => $s5['rate'],
    ]);

    return [$owner, $world, $sale];
}

/**
 * AT-2 #7 as form data.
 *
 * @return array<string, mixed>
 */
function at2Adjustment(): array
{
    $entry = collect(acceptance('at2')['entries'])->firstWhere('no', 7);

    return ['idempotency_key' => (string) Str::uuid(), 'date' => $entry['date'], 'amount' => $entry['amount'], 'reason' => $entry['reason'], 'note' => $entry['note']];
}

function aslamAdjustablePurchase(User $owner, array $world, ?string $rate = '50'): Purchase
{
    return app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '800', 'rate' => $rate,
    ]);
}

it('PR-07 AT-2 #7 2000 off sale s5', function () {
    [$owner, $world, $sale] = adjustmentWorld();

    $adjustment = app(CreateAdjustment::class)->handle($owner, $sale, at2Adjustment());
    $abc = $world['parties']['abc'];

    expect(journalLines($adjustment->journal_entry_id))->toBe([
        ['4900', '2000.00', '0.00', null, null],
        ['1200', '0.00', '2000.00', $abc->code, null],
    ])
        ->and(partyBalance($abc))->toBe(array_intersect_key(acceptance('at2')['expected']['parties']['abc'], ['words' => 0, 'amount' => 0]))
        ->and($sale->refresh()->amount)->toBe('63000.00')
        ->and($adjustment->voucher_no)->toBe('ADJ-000001')
        ->and($adjustment->party_id)->toBe($abc->id)
        ->and($adjustment->reason->value)->toBe('quality');
});

it('PR-08 purchase adjustment', function () {
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $purchase = aslamAdjustablePurchase($owner, $world);

    $adjustment = app(CreateAdjustment::class)->handle($owner, $purchase, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-06', 'amount' => '1500', 'reason' => 'weight', 'note' => 'Wet waste, agreed with Aslam',
    ]);

    expect(journalLines($adjustment->journal_entry_id))->toBe([
        ['2000', '1500.00', '0.00', 'S-0001', null],
        ['5900', '0.00', '1500.00', null, null],
    ])
        ->and(partyBalance($world['parties']['aslam']))->toBe(['words' => 'We owe', 'amount' => '38500.00']);
});

it('BR-ADJ-03 amount above the remaining is refused', function () {
    [$owner, , $sale] = adjustmentWorld();
    app(CreateAdjustment::class)->handle($owner, $sale, [...at2Adjustment(), 'amount' => '60000']);

    expect(fn () => app(CreateAdjustment::class)->handle($owner, $sale, [...at2Adjustment(), 'amount' => '3000.01']))
        ->toThrow(ValidationException::class, 'The most that can be adjusted is Rs 3,000.00.')
        ->and(app(CreateAdjustment::class)->handle($owner, $sale, [...at2Adjustment(), 'amount' => '3000'])->amount)->toBe('3000.00');
});

it('BR-ADJ-01 adjustment on a rate-pending entry is refused', function () {
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $purchase = aslamAdjustablePurchase($owner, $world, null);

    expect(fn () => app(CreateAdjustment::class)->handle($owner, $purchase, [...at2Adjustment(), 'amount' => '100']))
        ->toThrow(ValidationException::class, 'Only an active entry with a fixed rate can be adjusted.')
        ->and(Adjustment::query()->count())->toBe(0);
});

it('BR-PUR-13 sale with an adjustment cannot be cancelled', function () {
    [$owner, , $sale] = adjustmentWorld();
    $adjustment = app(CreateAdjustment::class)->handle($owner, $sale, at2Adjustment());

    expect(fn () => app(CancelSale::class)->handle($owner, $sale, 'Entered twice by mistake'))
        ->toThrow(ValidationException::class, 'Cancel the agreed adjustments first.');

    app(CancelAdjustment::class)->handle($owner, $adjustment, 'Buyer withdrew the claim');

    expect(app(CancelSale::class)->handle($owner, $sale, 'Entered twice by mistake')->status->value)->toBe('cancelled');
});

it('BR-PUR-13 purchase with an adjustment cannot be cancelled', function () {
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $purchase = aslamAdjustablePurchase($owner, $world);
    app(CreateAdjustment::class)->handle($owner, $purchase, [...at2Adjustment(), 'amount' => '100']);

    expect(fn () => app(CancelPurchase::class)->handle($owner, $purchase, 'Entered twice by mistake'))
        ->toThrow(ValidationException::class, 'Cancel the agreed adjustments first.');
});

it('T-18 BR-GEN-11 the same idempotency key twice creates one adjustment and one voucher', function () {
    [$owner, , $sale] = adjustmentWorld();
    $data = at2Adjustment();

    $first = app(CreateAdjustment::class)->handle($owner, $sale, $data);
    $second = app(CreateAdjustment::class)->handle($owner, $sale, $data);

    expect($second->id)->toBe($first->id)
        ->and(Adjustment::query()->count())->toBe(1)
        ->and(DB::table('journal_entries')->where('source_type', 'adjustment')->count())->toBe(1)
        ->and(DB::table('voucher_sequences')->where('doc_type', 'adjustment')->value('next_number'))->toBe(2);
});

it('BR-ADJ-04 and BR-ADJ-05 reason, note and a date on or after the entry are required', function () {
    [$owner, , $sale] = adjustmentWorld();

    expect(fn () => app(CreateAdjustment::class)->handle($owner, $sale, [...at2Adjustment(), 'note' => '  ']))
        ->toThrow(ValidationException::class, 'Write what was agreed.')
        ->and(fn () => app(CreateAdjustment::class)->handle($owner, $sale, [...at2Adjustment(), 'reason' => 'discount']))
        ->toThrow(ValidationException::class, 'Choose quality, weight, freight or other.')
        ->and(fn () => app(CreateAdjustment::class)->handle($owner, $sale, [...at2Adjustment(), 'date' => '2026-01-04']))
        ->toThrow(ValidationException::class, 'The date cannot be before the entry date.');
});

it('BR-ADJ-02 an edit that would change the party or go below the adjustments is refused', function () {
    [$owner, $world, $sale] = adjustmentWorld();
    app(CreateAdjustment::class)->handle($owner, $sale, [...at2Adjustment(), 'amount' => '60000']);
    $sale->refresh();
    $form = [
        'version' => $sale->updated_at?->toIso8601String(), 'date' => $sale->entry_date, 'party_id' => $sale->party_id,
        'item_id' => $sale->item_id, 'kg' => $sale->kg, 'rate' => $sale->rate,
    ];

    expect(fn () => app(UpdateSale::class)->handle($owner, $sale, [...$form, 'kg' => '250']))
        ->toThrow(ValidationException::class, 'Cancel the agreed adjustments first.')
        ->and(fn () => app(UpdateSale::class)->handle($owner, $sale, [...$form, 'party_id' => $world['parties']['rehman_traders']->id]))
        ->toThrow(ValidationException::class, 'Cancel the agreed adjustments first.')
        ->and(app(UpdateSale::class)->handle($owner, $sale, [...$form, 'kg' => '290'])->amount)->toBe('60900.00');
});

it('the sale detail page lists adjustments, the maximum, and the khata row text', function () {
    [$owner, $world, $sale] = adjustmentWorld();
    $this->actingAs($owner)->post(route('sales.adjustments.store', $sale), at2Adjustment())->assertRedirect();

    $this->actingAs($owner)->get(route('sales.show', $sale))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/show')
            ->where('adjustment_max', '61000.00')
            ->where('adjustments.0.voucher_no', 'ADJ-000001')
            ->where('adjustments.0.reason', 'quality'));

    $this->actingAs($owner)->get(route('parties.show', $world['parties']['abc']))
        ->assertInertia(fn (Assert $page) => $page->where('khata.rows', fn ($rows) => collect($rows)->contains(fn ($row) => ($row['description'] ?? null) === 'Agreed adjustment – quality')));

    $this->actingAs(User::factory()->create())->post(route('sales.adjustments.store', $sale), at2Adjustment())->assertForbidden();
});

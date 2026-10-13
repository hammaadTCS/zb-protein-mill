<?php

use App\Domain\Adjustments\Adjustments;
use App\Domain\Items\Models\Item;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Actions\CreateRateCorrection;
use App\Domain\Purchases\Actions\UpdatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateChange;
use App\Domain\Purchases\Models\RateCorrection;
use App\Domain\Sales\Actions\CreateSale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * The AT-1 world and one January purchase of 1,000 kg Waste at the given rate.
 *
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}, Purchase}
 */
function correctionWorld(string $rate = '50'): array
{
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $purchase = app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-10', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '1000', 'rate' => $rate,
    ]);

    return [$owner, $world, $purchase];
}

/** Closes January the way the card says (the real close comes in T7.1) and moves to February. */
function closeJanuary(): void
{
    freezeToday('2026-02-01');
    closeMonthForTest('2026-01');
    freezeToday('2026-02-05');
}

it('AT-5 a rate correction for an entry in a closed month posts only the difference', function () {
    $case = collect(acceptance('controls')['cases'])->firstWhere('id', 'AT-5');
    $owner = owner();
    freezeToday($case['today']);
    $world = acceptanceWorld($case, $owner);
    $refs = [];

    foreach ($case['steps'] as $step) {
        match ($step['type']) {
            'purchase' => $refs[$step['ref']] = app(CreatePurchase::class)->handle($owner, [
                'idempotency_key' => (string) Str::uuid(), 'date' => $step['date'], 'party_id' => $world['parties'][$step['party']]->id,
                'item_id' => Item::query()->where('name', $step['item'])->value('id'), 'kg' => $step['kg'], 'rate' => $step['rate'],
            ]),
            'set_today' => freezeToday($step['date']),
            'cash_count' => null, // the daily cash check comes in T5.1; T7.7 runs AT-5 fully
            'close_month' => closeMonthForTest($step['period']),
            'attempt' => expect(function () use ($owner, $refs, $step) {
                $purchase = $refs[$step['action']['target']]->refresh();
                app(UpdatePurchase::class)->handle($owner, $purchase, [
                    'version' => $purchase->updated_at?->toIso8601String(), 'date' => $purchase->entry_date, 'party_id' => $purchase->party_id,
                    'item_id' => $purchase->item_id, 'kg' => $purchase->kg, 'rate' => $purchase->rate, ...$step['action']['changes'],
                ]);
            })->toThrow(ValidationException::class, $step['expect_error_contains']),
            'rate_correction' => $refs['correction'] = app(CreateRateCorrection::class)->handle($owner, $refs[$step['target']], $step['new_rate'], $step['date'], $step['reason']),
        };
    }

    $expected = $case['expected'];
    $correction = $refs['correction'];
    $p1 = $refs['p1']->refresh();

    expect(partyBalance($world['parties']['aslam']))->toBe($expected['parties']['aslam'])
        ->and(['rate' => $p1->rate, 'amount' => $p1->amount])->toBe($expected['purchase_p1_unchanged'])
        ->and([
            'voucher' => $correction->voucher_no,
            'correction_date' => $correction->correction_date,
            'old_rate' => $correction->old_rate,
            'new_rate' => $correction->new_rate,
            'old_amount' => $correction->old_amount,
            'new_amount' => $correction->new_amount,
            'difference' => $correction->difference,
        ])->toBe($expected['rate_correction'])
        ->and(DB::table('journal_entries')->where('id', $correction->journal_entry_id)->value('posting_date'))->toBe($expected['rate_correction_journal']['posting_date'])
        ->and(array_map(fn (array $line) => [$line[0], $line[1], $line[2], $line[3]], journalLines($correction->journal_entry_id)))
        ->toBe(array_map(fn (array $line) => [
            $line['account'], $line['debit'], $line['credit'], $line['party'] === null ? null : $world['parties'][$line['party']]->code,
        ], $expected['rate_correction_journal']['lines']))
        ->and(RateChange::query()->where('target_id', $p1->id)->latest('id')->first()?->reason)->toBe('Quality lower than agreed');
});

it('PR-26 increase: rate 50 to 52 on 1000 kg posts Dr 5000 2000.00 / Cr 2000 2000.00', function () {
    [$owner, , $purchase] = correctionWorld();
    closeJanuary();

    $correction = app(CreateRateCorrection::class)->handle($owner, $purchase, '52', '2026-02-05', 'Rate agreed later');

    expect(journalLines($correction->journal_entry_id))->toBe([
        ['5000', '2000.00', '0.00', null, 'Waste'],
        ['2000', '0.00', '2000.00', 'S-0001', null],
    ])
        ->and($correction->difference)->toBe('2000.00');
});

it('PR-27 sale decrease posts Dr 4000 / Cr 1200', function () {
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $sale = app(CreateSale::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-10', 'party_id' => $world['parties']['abc']->id,
        'item_id' => Item::query()->where('code', 'PROTEIN')->value('id'), 'kg' => '300', 'rate' => '210',
    ]);
    closeJanuary();

    $correction = app(CreateRateCorrection::class)->handle($owner, $sale, '200', '2026-02-05', 'Price cut agreed');

    expect(journalLines($correction->journal_entry_id))->toBe([
        ['4000', '3000.00', '0.00', null, 'Protein product'],
        ['1200', '0.00', '3000.00', 'B-0001', null],
    ])
        ->and(partyBalance($world['parties']['abc']))->toBe(['words' => 'Owes us', 'amount' => '60000.00'])
        ->and($sale->refresh()->amount)->toBe('63000.00');
});

it('BR-LED-09 a second correction uses the corrected amount as the base', function () {
    [$owner, $world, $purchase] = correctionWorld();
    closeJanuary();
    app(CreateRateCorrection::class)->handle($owner, $purchase, '52', '2026-02-05', 'Rate agreed later');

    $second = app(CreateRateCorrection::class)->handle($owner, $purchase, '51', '2026-02-05', 'Settled at 51');

    expect([$second->old_rate, $second->new_rate, $second->old_amount, $second->new_amount, $second->difference])
        ->toBe(['52.0000', '51.0000', '52000.00', '51000.00', '-1000.00'])
        ->and(journalLines($second->journal_entry_id))->toBe([
            ['2000', '1000.00', '0.00', 'S-0001', null],
            ['5000', '0.00', '1000.00', null, 'Waste'],
        ])
        ->and(partyBalance($world['parties']['aslam']))->toBe(['words' => 'We owe', 'amount' => '51000.00'])
        ->and(Adjustments::maximum($purchase->refresh()))->toBe('51000.00')
        ->and(fn () => app(CreateRateCorrection::class)->handle($owner, $purchase, '51', '2026-02-05', 'Again'))
        ->toThrow(ValidationException::class, 'The new rate gives the same amount.');
});

it('BR-PUR-14 a correction in an open month entry is refused', function () {
    [$owner, , $purchase] = correctionWorld();

    expect(fn () => app(CreateRateCorrection::class)->handle($owner, $purchase, '52', '2026-01-31', 'Use edit'))
        ->toThrow(ValidationException::class, 'This entry is in an open month. Edit it instead.')
        ->and(RateCorrection::query()->count())->toBe(0);
});

it('the detail page of a closed-month entry offers Correct rate and shows corrections; the khata shows the row', function () {
    [$owner, $world, $purchase] = correctionWorld();
    closeJanuary();

    $this->actingAs($owner)->post(route('purchases.rate-correction', $purchase), ['rate' => '48', 'date' => '2026-02-05', 'reason' => 'Quality lower than agreed'])
        ->assertRedirect();

    $this->actingAs($owner)->get(route('purchases.show', $purchase))
        ->assertInertia(fn (Assert $page) => $page
            ->where('month_closed', true)
            ->where('effective', ['rate' => '48.0000', 'amount' => '48000.00'])
            ->where('rate_corrections.0.voucher_no', 'RCO-000001')
            ->where('rate_corrections.0.difference', '-2000.00'));

    $this->actingAs($owner)->get(route('parties.show', $world['parties']['aslam']))
        ->assertInertia(fn (Assert $page) => $page->where('khata.rows', fn ($rows) => collect($rows)->contains(fn ($row) => ($row['description'] ?? null) === 'Rate correction PUR-000001 – 50 → 48')));

    $this->actingAs(accountant())->post(route('purchases.rate-correction', $purchase), ['rate' => '47', 'date' => '2026-02-05', 'reason' => 'x'])
        ->assertSessionHasErrors('reason');
    $this->actingAs(User::factory()->create())->post(route('purchases.rate-correction', $purchase), ['rate' => '47', 'date' => '2026-02-05', 'reason' => 'Lower quality'])
        ->assertForbidden();

    expect(RateCorrection::query()->count())->toBe(1);
});

<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Items\Models\Item;
use App\Domain\Purchases\Actions\AddPurchaseRate;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateChange;
use App\Domain\Reports\Queries\PartyKhata;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $entry  an AT-1 purchase entry
 * @param  array{accounts: array<string, mixed>, parties: array<string, mixed>}  $world
 */
function at1Purchase(User $by, array $entry, array $world): Purchase
{
    $data = [
        'idempotency_key' => (string) Str::uuid(),
        'date' => $entry['date'],
        'party_id' => $world['parties'][$entry['party']]->id,
        'item_id' => Item::query()->where('name', $entry['item'])->value('id'),
        'kg' => $entry['kg'],
        'rate' => $entry['rate'],
    ];

    if (isset($entry['paid_now'])) {
        $data['paid_now'] = ['amount' => $entry['paid_now']['amount'], 'money_account_id' => $world['accounts'][$entry['paid_now']['money_account']]->id];
    }

    return app(CreatePurchase::class)->handle($by, $data);
}

/** The AT-1 entry with this number. */
function at1Entry(int $no): array
{
    return collect(acceptance('at1')['entries'])->firstWhere('no', $no);
}

function pendingPurchase(User $by, int $partyId, string $date, string $kg = '400'): Purchase
{
    return app(CreatePurchase::class)->handle($by, [
        'idempotency_key' => (string) Str::uuid(), 'date' => $date, 'party_id' => $partyId,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => $kg, 'rate' => null,
    ]);
}

it('T-05 a rate-pending purchase creates no journal and no balance change', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $journals = DB::table('journal_entries')->count();

    $purchase = at1Purchase($owner, at1Entry(4), $world);

    expect($purchase->rate_status->value)->toBe('pending')
        ->and($purchase->rate)->toBeNull()
        ->and($purchase->amount)->toBeNull()
        ->and($purchase->journal_entry_id)->toBeNull()
        ->and(DB::table('journal_entries')->count())->toBe($journals)
        ->and(partyBalance($world['parties']['karim']))->toBe(['words' => 'Settled', 'amount' => '0.00'])
        ->and($world['parties']['karim']->balance()->pendingKg)->toBe('400.000');
});

it('T-05 adding the rate posts PR-01 and writes a rate_changes row', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $purchase = at1Purchase($owner, at1Entry(4), $world);

    $purchase = app(AddPurchaseRate::class)->handle($owner, $purchase, '49', '2026-01-06');
    $change = RateChange::query()->sole();

    expect($purchase->amount)->toBe('19600.00')
        ->and($purchase->rate_status->value)->toBe('fixed')
        ->and($purchase->rate_fixed_at)->not->toBeNull()
        ->and(journalLines($purchase->journal_entry_id))->toBe([
            ['5000', '19600.00', '0.00', null, 'Waste'],
            ['2000', '0.00', '19600.00', 'S-0002', null],
        ])
        ->and(DB::table('journal_entries')->where('id', $purchase->journal_entry_id)->value('posting_date'))->toBe('2026-01-05')
        ->and([$change->target_type, $change->target_id, $change->old_rate, $change->new_rate, $change->old_amount, $change->new_amount])
        ->toBe(['purchase', $purchase->id, null, '49.0000', null, '19600.00'])
        ->and(AuditLog::query()->where('action', 'rate_added')->sole()->auditable_id)->toBe($purchase->id)
        ->and($world['parties']['karim']->balance()->pendingKg)->toBe('0.000');
});

it('AT-1 #4 and #12: Karim pending 400 kg, then rate 49', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    at1Purchase($owner, at1Entry(1), $world);
    at1Purchase($owner, at1Entry(2), $world);
    $pending = at1Purchase($owner, at1Entry(4), $world);
    $add = at1Entry(12);

    $added = app(AddPurchaseRate::class)->handle($owner, $pending, $add['rate'], $add['date']);

    expect($added->amount)->toBe('19600.00')
        ->and(partyBalance($world['parties']['karim']))->toBe(['words' => 'We owe', 'amount' => '38400.00']);
});

it('BR-LED-08 rate added in February for a January entry in a closed January posts on the February date', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $purchase = pendingPurchase($owner, $world['parties']['karim']->id, '2026-01-20');

    closeMonthForTest('2026-01');
    freezeToday('2026-02-05');

    $purchase = app(AddPurchaseRate::class)->handle($owner, $purchase, '49', '2026-02-05');

    expect(DB::table('journal_entries')->where('id', $purchase->journal_entry_id)->value('posting_date'))->toBe('2026-02-05')
        ->and($purchase->entry_date)->toBe('2026-01-20');
});

it('BR-PUR-04 only an active rate-pending purchase gets a rate', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $fixed = at1Purchase($owner, at1Entry(1), $world);

    expect(fn () => app(AddPurchaseRate::class)->handle($owner, $fixed, '49', '2026-01-06'))
        ->toThrow(ValidationException::class, 'Only an active rate-pending entry can get a rate.');
});

it('BR-PUR-10 paid now on a rate-pending purchase shows an advance until the rate is added', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);

    app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['bashir']->id,
        'item_id' => Item::query()->where('code', 'WOOD')->value('id'), 'kg' => '1000', 'rate' => '',
        'paid_now' => ['amount' => '5000', 'money_account_id' => $world['accounts']['cash']->id],
    ]);

    expect(partyBalance($world['parties']['bashir']))->toBe(['words' => 'Advance', 'amount' => '5000.00']);
});

it('R-01 the khata shows a rate-pending row with kg and no amount', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    at1Purchase($owner, at1Entry(2), $world);
    at1Purchase($owner, at1Entry(4), $world);

    $khata = PartyKhata::for($world['parties']['karim']);
    $pendingRow = $khata->rows[2];

    expect(count($khata->rows))->toBe(3)
        ->and([$pendingRow->voucher, $pendingRow->description, $pendingRow->increase, $pendingRow->decrease, $pendingRow->pendingKg])
        ->toBe(['PUR-000002', 'Waste 400 kg – Rate pending', null, null, '400.000'])
        ->and([$pendingRow->balance->words, $pendingRow->balance->amount])->toBe(['We owe', '18800.00'])
        ->and($khata->closing->pendingKg)->toBe('400.000');
});

it('BR-CHK-05 an entry 8 days old is flagged when the limit is 7', function () {
    freezeToday('2026-02-05');
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    DB::table('settings')->where('key', 'rate_pending_age_days')->update(['value' => '7']);
    pendingPurchase($owner, $world['parties']['karim']->id, '2026-01-28');
    pendingPurchase($owner, $world['parties']['aslam']->id, '2026-01-29');

    $this->actingAs($owner)->get(route('rate-pending.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('rate-pending/index')
            ->where('rows.0.age_days', 8)
            ->where('rows.0.aged', true)
            ->where('rows.1.age_days', 7)
            ->where('rows.1.aged', false));
});

it('the add-rate route needs purchase.update and saves the rate', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $purchase = pendingPurchase($owner, $world['parties']['karim']->id, '2026-01-05');

    $this->actingAs(User::factory()->create())->post(route('purchases.rate', $purchase), ['rate' => '49', 'date' => '2026-01-06'])->assertForbidden();

    $this->actingAs(accountant())->post(route('purchases.rate', $purchase), ['rate' => '49', 'date' => '2026-01-06'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('success', 'Saved. PUR-000001');

    expect($purchase->refresh()->amount)->toBe('19600.00');
});

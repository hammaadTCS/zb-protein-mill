<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Items\Models\Item;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Actions\CancelPurchase;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Actions\UpdatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateChange;
use App\Models\User;
use App\Support\ConcurrentEdit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}}
 */
function editWorld(): array
{
    $owner = owner();

    return [$owner, acceptanceWorld(acceptance('at1'), $owner)];
}

/**
 * @param  array<string, mixed>  $extra
 */
function aslamPurchase(User $by, array $world, array $extra = []): Purchase
{
    return app(CreatePurchase::class)->handle($by, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '800', 'rate' => '50', ...$extra,
    ]);
}

/**
 * The edit form of a purchase with some fields changed.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function editForm(Purchase $purchase, array $changes): array
{
    $purchase->refresh();

    return [
        'version' => $purchase->updated_at?->toIso8601String(),
        'date' => $purchase->entry_date,
        'party_id' => $purchase->party_id,
        'item_id' => $purchase->item_id,
        'kg' => $purchase->kg,
        'rate' => $purchase->rate,
        ...$changes,
    ];
}

it('T-06 editing kg reverses and reposts', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world);
    $original = $purchase->journal_entry_id;

    $edited = app(UpdatePurchase::class)->handle($owner, $purchase, editForm($purchase, ['kg' => '820']));
    $reversal = DB::table('journal_entries')->where('reversal_of_id', $original)->sole();

    expect(journalLines($original))->toBe([
        ['5000', '40000.00', '0.00', null, 'Waste'],
        ['2000', '0.00', '40000.00', 'S-0001', null],
    ])
        ->and(journalLines($reversal->id))->toBe([
            ['5000', '0.00', '40000.00', null, 'Waste'],
            ['2000', '40000.00', '0.00', 'S-0001', null],
        ])
        ->and($reversal->posting_date)->toBe('2026-01-05')
        ->and(journalLines($edited->journal_entry_id))->toBe([
            ['5000', '41000.00', '0.00', null, 'Waste'],
            ['2000', '0.00', '41000.00', 'S-0001', null],
        ])
        ->and($edited->voucher_no)->toBe('PUR-000001')
        ->and($edited->amount)->toBe('41000.00')
        ->and(partyBalance($world['parties']['aslam']))->toBe(['words' => 'We owe', 'amount' => '41000.00'])
        ->and(AuditLog::query()->where('action', 'updated')->where('auditable_id', $edited->id)->sole()->new_values)
        ->toBe(['kg' => '820.000', 'amount' => '41000.00']);
});

it('T-06 cancelling reverses the journal and keeps the row', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world);

    $cancelled = app(CancelPurchase::class)->handle($owner, $purchase, 'Entered twice by mistake');

    expect($cancelled->status->value)->toBe('cancelled')
        ->and($cancelled->cancel_reason)->toBe('Entered twice by mistake')
        ->and($cancelled->cancelled_by)->toBe($owner->id)
        ->and(Purchase::query()->count())->toBe(1)
        ->and(DB::table('journal_entries')->where('reversal_of_id', $purchase->journal_entry_id)->exists())->toBeTrue()
        ->and(partyBalance($world['parties']['aslam']))->toBe(['words' => 'Settled', 'amount' => '0.00'])
        ->and(AuditLog::query()->where('action', 'cancelled')->sole()->reason)->toBe('Entered twice by mistake');
});

it('BR-PUR-12 cancelling without the linked payment leaves an advance', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world, ['paid_now' => ['amount' => '40000', 'money_account_id' => $world['accounts']['cash']->id]]);

    app(CancelPurchase::class)->handle($owner, $purchase, 'Wrong supplier', false);

    expect(partyBalance($world['parties']['aslam']))->toBe(['words' => 'Advance', 'amount' => '40000.00'])
        ->and(Payment::query()->sole()->status->value)->toBe('active');
});

it('BR-PUR-12 cancelling with the linked payment cancels both', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world, ['paid_now' => ['amount' => '40000', 'money_account_id' => $world['accounts']['cash']->id]]);

    app(CancelPurchase::class)->handle($owner, $purchase, 'Wrong supplier', true);

    expect(partyBalance($world['parties']['aslam']))->toBe(['words' => 'Settled', 'amount' => '0.00'])
        ->and(Payment::query()->sole()->status->value)->toBe('cancelled')
        ->and($world['accounts']['cash']->balance())->toBe('80000.00');
});

it('BR-GEN-04 a 4-character reason is refused', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world);

    expect(fn () => app(CancelPurchase::class)->handle($owner, $purchase, 'oops'))
        ->toThrow(ValidationException::class, 'Give a reason of at least 5 characters.');
    expect($purchase->refresh()->status->value)->toBe('active');
});

it('BR-GEN-09 a stale version is refused', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world);
    $staleForm = editForm($purchase, ['kg' => '820']);

    $this->travel(5)->seconds();
    app(UpdatePurchase::class)->handle($owner, $purchase, editForm($purchase, ['kg' => '810'])); // another user saved first

    expect(fn () => app(UpdatePurchase::class)->handle($owner, $purchase, $staleForm))
        ->toThrow(ConcurrentEdit::class, 'This entry was changed by another user. Reload.');

    $this->actingAs($owner)->put(route('purchases.update', $purchase), $staleForm)
        ->assertStatus(409)
        ->assertJson(['message' => 'This entry was changed by another user. Reload.']);
});

it('BR-PUR-14 a purchase in a closed month cannot be edited or cancelled', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world);
    closeMonthForTest('2026-01');
    freezeToday('2026-02-05');

    expect(fn () => app(UpdatePurchase::class)->handle($owner, $purchase, editForm($purchase, ['kg' => '820', 'date' => '2026-02-02'])))
        ->toThrow(ValidationException::class, 'January 2026 is closed. Make this change as a correction in an open month.')
        ->and(fn () => app(CancelPurchase::class)->handle($owner, $purchase, 'Entered twice by mistake'))
        ->toThrow(ValidationException::class, 'January 2026 is closed.');
});

it('changing a fixed rate writes rate_changes with old and new values', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world);

    app(UpdatePurchase::class)->handle($owner, $purchase, editForm($purchase, ['rate' => '52']));
    $change = RateChange::query()->sole();

    expect([$change->old_rate, $change->new_rate, $change->old_amount, $change->new_amount])
        ->toBe(['50.0000', '52.0000', '40000.00', '41600.00'])
        ->and(AuditLog::query()->where('action', 'rate_changed')->sole()->new_values)->toBe(['rate' => '52.0000', 'amount' => '41600.00']);
});

it('BR-PUR-11 a rate-pending purchase is edited without any journal', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world, ['rate' => null]);
    $journals = DB::table('journal_entries')->count();

    $edited = app(UpdatePurchase::class)->handle($owner, $purchase, editForm($purchase, ['kg' => '900']));

    expect($edited->kg)->toBe('900.000')
        ->and($edited->journal_entry_id)->toBeNull()
        ->and(DB::table('journal_entries')->count())->toBe($journals);
});

it('BR-PTY-04 an edit keeps a supplier as the party', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world);

    expect(fn () => app(UpdatePurchase::class)->handle($owner, $purchase, editForm($purchase, ['party_id' => $world['parties']['abc']->id])))
        ->toThrow(ValidationException::class, 'Select an active supplier.');
});

it('edit and cancel routes need purchase.update and purchase.cancel', function () {
    [$owner, $world] = editWorld();
    $purchase = aslamPurchase($owner, $world);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('purchases.edit', $purchase))->assertForbidden();
    $this->actingAs($stranger)->post(route('purchases.cancel', $purchase), ['reason' => 'Wrong entry'])->assertForbidden();

    $this->actingAs(accountant())->post(route('purchases.cancel', $purchase), ['reason' => 'Wrong entry'])
        ->assertRedirect(route('purchases.show', $purchase));

    expect($purchase->refresh()->status->value)->toBe('cancelled');
});

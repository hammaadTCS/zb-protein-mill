<?php

use App\Domain\Items\Models\Item;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Payments\Actions\UpdatePayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Actions\AddPurchaseRate;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Sales\Actions\CreateSale;
use App\Models\User;
use App\Support\Warnings\WarningRequired;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * The AT-1 world plus an employee and an other party.
 *
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}}
 */
function paymentsWorld(): array
{
    $owner = owner();
    $at1 = acceptance('at1');
    $at1['parties'][] = ['key' => 'nadeem', 'type' => 'employee', 'name' => 'Nadeem', 'mobile' => '03000000010'];
    $at1['parties'][] = ['key' => 'transporter', 'type' => 'other', 'name' => 'Test Transporter', 'mobile' => '03000000009'];

    return [$owner, acceptanceWorld($at1, $owner)];
}

/**
 * @param  array<string, mixed>  $data
 * @param  list<string>  $confirmed
 */
function pay(User $by, array $data, array $confirmed = []): Payment
{
    return app(CreatePayment::class)->handle($by, ['idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-06', ...$data], $confirmed);
}

/**
 * An AT-1 payment entry as form data.
 *
 * @param  array<string, mixed>  $entry
 * @return array<string, mixed>
 */
function paymentData(array $entry, array $world): array
{
    return [
        'date' => $entry['date'],
        'direction' => $entry['direction'],
        'party_id' => $world['parties'][$entry['party']]->id,
        'amount' => $entry['amount'],
        'money_account_id' => $world['accounts'][$entry['money_account']]->id,
        'reference_no' => $entry['reference_no'] ?? null,
    ];
}

function at1No(int|string $no): array
{
    return collect(acceptance('at1')['entries'])->firstWhere('no', $no);
}

/** Runs an AT-1 purchase or sale entry. */
function runAt1Entry(User $by, int $no, array $world): mixed
{
    $entry = at1No($no);
    $data = [
        'idempotency_key' => (string) Str::uuid(), 'date' => $entry['date'], 'party_id' => $world['parties'][$entry['party']]->id,
        'item_id' => Item::query()->where('name', $entry['item'])->value('id'), 'kg' => $entry['kg'], 'rate' => $entry['rate'],
    ];

    foreach (['paid_now', 'received_now'] as $key) {
        if (isset($entry[$key])) {
            $data[$key] = ['amount' => $entry[$key]['amount'], 'money_account_id' => $world['accounts'][$entry[$key]['money_account']]->id, 'reference_no' => $entry[$key]['reference_no'] ?? null];
        }
    }

    return $entry['type'] === 'sale' ? app(CreateSale::class)->handle($by, $data) : app(CreatePurchase::class)->handle($by, $data);
}

it('AT-1 #3 Bashir paid 20000 HBL', function () {
    [$owner, $world] = paymentsWorld();

    $payment = pay($owner, paymentData(at1No(3), $world));

    expect($payment->voucher_no)->toBe('PAY-000001')
        ->and(partyBalance($world['parties']['bashir']))->toBe(['words' => 'Advance', 'amount' => '20000.00']);
});

it('AT-1 #13 ABC received 40000 and #14 Rehman received 30000', function () {
    [$owner, $world] = paymentsWorld();
    runAt1Entry($owner, 5, $world);
    runAt1Entry($owner, 6, $world);

    $abc = pay($owner, paymentData(at1No(13), $world));
    pay($owner, paymentData(at1No(14), $world));

    expect($abc->voucher_no)->toBe('RCV-000002') // RCV-000001 is #6's "received now"
        ->and(partyBalance($world['parties']['abc']))->toBe(['words' => 'Owes us', 'amount' => '23000.00'])
        ->and(partyBalance($world['parties']['rehman']))->toBe(['words' => 'Advance', 'amount' => '30000.00']);
});

it('AT-1 #18 Karim paid 38400', function () {
    [$owner, $world] = paymentsWorld();
    runAt1Entry($owner, 2, $world);
    $pending = runAt1Entry($owner, 4, $world);
    app(AddPurchaseRate::class)->handle($owner, $pending, at1No(12)['rate'], at1No(12)['date']);

    pay($owner, paymentData(at1No(18), $world));

    expect(partyBalance($world['parties']['karim']))->toBe(['words' => 'Settled', 'amount' => '0.00']);
});

dataset('mapping', [
    'supplier paid PR-02' => ['aslam', 'paid', [['2000', '100.00', '0.00', 'S-0001', null], ['1010', '0.00', '100.00', null, null]]],
    'supplier received PR-03' => ['aslam', 'received', [['1010', '100.00', '0.00', null, null], ['2000', '0.00', '100.00', 'S-0001', null]]],
    'buyer paid PR-06' => ['abc', 'paid', [['1200', '100.00', '0.00', 'B-0001', null], ['1010', '0.00', '100.00', null, null]]],
    'buyer received PR-05' => ['abc', 'received', [['1010', '100.00', '0.00', null, null], ['1200', '0.00', '100.00', 'B-0001', null]]],
    'other paid PR-12' => ['transporter', 'paid', [['2100', '100.00', '0.00', 'O-0001', null], ['1010', '0.00', '100.00', null, null]]],
    'other received PR-13' => ['transporter', 'received', [['1010', '100.00', '0.00', null, null], ['2100', '0.00', '100.00', 'O-0001', null]]],
    'employee paid PR-12' => ['nadeem', 'paid', [['1300', '100.00', '0.00', 'E-0001', null], ['1010', '0.00', '100.00', null, null]]],
    'employee received PR-13' => ['nadeem', 'received', [['1010', '100.00', '0.00', null, null], ['1300', '0.00', '100.00', 'E-0001', null]]],
]);

it('spec §8.3 payment mapping', function (string $party, string $direction, array $lines) {
    [$owner, $world] = paymentsWorld();

    $payment = pay($owner, ['direction' => $direction, 'party_id' => $world['parties'][$party]->id, 'amount' => '100', 'money_account_id' => $world['accounts']['cash']->id]);

    expect(journalLines($payment->journal_entry_id))->toBe($lines)
        ->and($payment->voucher_no)->toBe($direction === 'paid' ? 'PAY-000001' : 'RCV-000001');
})->with('mapping');

it('BR-PAY-01 partner is refused', function () {
    [$owner, $world] = paymentsWorld();

    expect(fn () => pay($owner, ['direction' => 'paid', 'party_id' => $world['parties']['ali']->id, 'amount' => '100', 'money_account_id' => $world['accounts']['cash']->id]))
        ->toThrow(ValidationException::class, 'Select an active party. Partner money is entered on the partner page.');
});

it('BR-PAY-12 handled by a supplier is refused', function () {
    [$owner, $world] = paymentsWorld();

    expect(fn () => pay($owner, ['direction' => 'paid', 'party_id' => $world['parties']['aslam']->id, 'amount' => '100', 'money_account_id' => $world['accounts']['cash']->id, 'handled_by_party_id' => $world['parties']['karim']->id]))
        ->toThrow(ValidationException::class, 'Handled by must be an active employee.');
});

it('BR-PAY-04 bank payment without reference warns', function () {
    [$owner, $world] = paymentsWorld();
    $data = ['direction' => 'paid', 'party_id' => $world['parties']['aslam']->id, 'amount' => '100', 'money_account_id' => $world['accounts']['hbl']->id];

    expect(fn () => pay($owner, $data))->toThrow(WarningRequired::class, 'No transfer reference entered for a bank payment.');
    expect(pay($owner, $data, ['missing_reference'])->voucher_no)->toBe('PAY-000001');
});

it('BR-PAY-04 cash payment without reference does not warn', function () {
    [$owner, $world] = paymentsWorld();

    expect(pay($owner, ['direction' => 'paid', 'party_id' => $world['parties']['aslam']->id, 'amount' => '100', 'money_account_id' => $world['accounts']['cash']->id])->voucher_no)->toBe('PAY-000001');
});

it('BR-PAY-11 overpayment returns information and saves', function () {
    [$owner, $world] = paymentsWorld();
    runAt1Entry($owner, 2, $world); // Karim: we owe 18800.00

    $this->actingAs($owner)->post(route('payments.store'), [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-06', 'direction' => 'paid',
        'party_id' => $world['parties']['karim']->id, 'amount' => '20000', 'money_account_id' => $world['accounts']['cash']->id,
    ])->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'This is more than the balance. The extra Rs 1,200.00 will show as an advance.');

    expect(partyBalance($world['parties']['karim']))->toBe(['words' => 'Advance', 'amount' => '1200.00']);
});

it('BR-PAY-05 balance preview shows the balance now and after', function () {
    [$owner, $world] = paymentsWorld();
    runAt1Entry($owner, 2, $world);

    $this->actingAs($owner)->getJson(route('payments.balance-preview', ['party_id' => $world['parties']['karim']->id, 'direction' => 'paid', 'amount' => '10000']))
        ->assertOk()
        ->assertExactJson([
            'current' => ['words' => 'We owe', 'amount' => '18800.00'],
            'after' => ['words' => 'We owe', 'amount' => '8800.00'],
            'info' => null,
        ]);
});

it('editing direction keeps the voucher number', function () {
    [$owner, $world] = paymentsWorld();
    $payment = pay($owner, ['direction' => 'paid', 'party_id' => $world['parties']['aslam']->id, 'amount' => '500', 'money_account_id' => $world['accounts']['cash']->id]);
    $original = $payment->journal_entry_id;

    $edited = app(UpdatePayment::class)->handle($owner, $payment, [
        'version' => $payment->fresh()?->updated_at?->toIso8601String(),
        'date' => '2026-01-06', 'direction' => 'received', 'party_id' => $world['parties']['aslam']->id,
        'amount' => '500', 'money_account_id' => $world['accounts']['cash']->id,
    ]);

    expect($edited->voucher_no)->toBe('PAY-000001')
        ->and($edited->direction->value)->toBe('received')
        ->and(DB::table('journal_entries')->where('reversal_of_id', $original)->exists())->toBeTrue()
        ->and(journalLines($edited->journal_entry_id))->toBe([
            ['1010', '500.00', '0.00', null, null],
            ['2000', '0.00', '500.00', 'S-0001', null],
        ])
        ->and(partyBalance($world['parties']['aslam']))->toBe(['words' => 'We owe', 'amount' => '500.00']);
});

it('T-18 BR-GEN-11 the same idempotency key twice creates one payment and one voucher', function () {
    [$owner, $world] = paymentsWorld();
    $form = ['idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-06', 'direction' => 'paid', 'party_id' => $world['parties']['aslam']->id, 'amount' => '500', 'money_account_id' => $world['accounts']['cash']->id];

    $this->actingAs($owner)->post(route('payments.store'), $form)->assertInertiaFlash('success', 'Saved. PAY-000001');
    $this->actingAs($owner)->post(route('payments.store'), $form)->assertInertiaFlash('success', 'Already saved. PAY-000001');

    expect(Payment::query()->count())->toBe(1)
        ->and(DB::table('voucher_sequences')->where('doc_type', 'payment_paid')->value('next_number'))->toBe(2);
});

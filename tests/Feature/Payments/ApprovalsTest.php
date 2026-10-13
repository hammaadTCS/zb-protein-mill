<?php

use App\Domain\Items\Models\Item;
use App\Domain\Payments\Actions\ApprovePayment;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Payments\Actions\RejectPayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Reports\Queries\PartyKhata;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}}
 */
function approvalsWorld(): array
{
    $owner = owner();

    return [$owner, acceptanceWorld(acceptance('at1'), $owner)];
}

function payAs(User $by, int $partyId, int $accountId, string $amount, string $date = '2026-01-15'): Payment
{
    return app(CreatePayment::class)->handle($by, [
        'idempotency_key' => (string) Str::uuid(), 'date' => $date, 'direction' => 'paid',
        'party_id' => $partyId, 'amount' => $amount, 'money_account_id' => $accountId,
    ]);
}

it('T-08 accountant payment of 60000 with limit 50000 is pending with no journal', function () {
    [, $world] = approvalsWorld();
    $journals = DB::table('journal_entries')->count();

    $payment = payAs(accountant(), $world['parties']['aslam']->id, $world['accounts']['cash']->id, '60000');

    expect($payment->approval_status->value)->toBe('pending')
        ->and($payment->journal_entry_id)->toBeNull()
        ->and(DB::table('journal_entries')->count())->toBe($journals)
        ->and($world['accounts']['cash']->balance())->toBe('80000.00');
});

it('T-08 owner payment of 60000 posts immediately', function () {
    [$owner, $world] = approvalsWorld();

    $payment = payAs($owner, $world['parties']['aslam']->id, $world['accounts']['cash']->id, '60000');

    expect($payment->approval_status->value)->toBe('not_required')
        ->and($payment->journal_entry_id)->not->toBeNull();
});

it('T-08 first payment rule', function () {
    [$owner, $world] = approvalsWorld();
    DB::table('settings')->where('key', 'new_party_first_payment_limit')->update(['value' => '0.00']);
    $accountant = accountant();

    $first = payAs($accountant, $world['parties']['karim']->id, $world['accounts']['cash']->id, '1000');
    expect($first->approval_status->value)->toBe('pending');

    app(ApprovePayment::class)->handle($owner, $first);
    $second = payAs($accountant, $world['parties']['karim']->id, $world['accounts']['cash']->id, '1000');

    expect($second->approval_status->value)->toBe('not_required');
});

it('T-08 rejected payment never posts', function () {
    [$owner, $world] = approvalsWorld();
    $payment = payAs(accountant(), $world['parties']['aslam']->id, $world['accounts']['cash']->id, '60000');

    $rejected = app(RejectPayment::class)->handle($owner, $payment, 'Not agreed with the supplier');

    expect($rejected->approval_status->value)->toBe('rejected')
        ->and($rejected->rejection_reason)->toBe('Not agreed with the supplier')
        ->and($rejected->journal_entry_id)->toBeNull()
        ->and(Payment::query()->count())->toBe(1)
        ->and(fn () => app(ApprovePayment::class)->handle($owner, $rejected))->toThrow(ValidationException::class);
});

it('BR-PAY-08 approval date rule when the entry month is closed', function () {
    [$owner, $world] = approvalsWorld();
    $payment = payAs(accountant(), $world['parties']['aslam']->id, $world['accounts']['cash']->id, '60000', '2026-01-20');
    closeMonthForTest('2026-01');
    freezeToday('2026-02-05');

    $approved = app(ApprovePayment::class)->handle($owner, $payment);

    expect(DB::table('journal_entries')->where('id', $approved->journal_entry_id)->value('posting_date'))->toBe('2026-02-05')
        ->and($approved->entry_date)->toBe('2026-01-20');
});

it('BR-PAY-10 receipts never need approval', function () {
    [, $world] = approvalsWorld();

    $receipt = app(CreatePayment::class)->handle(accountant(), [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-15', 'direction' => 'received',
        'party_id' => $world['parties']['abc']->id, 'amount' => '90000', 'money_account_id' => $world['accounts']['cash']->id,
    ]);

    expect($receipt->approval_status->value)->toBe('not_required');
});

it('BR-PUR-10 a large "paid now" by the accountant waits for approval', function () {
    [, $world] = approvalsWorld();

    $purchase = app(CreatePurchase::class)->handle(accountant(), [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-10', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '1200', 'rate' => '50',
        'paid_now' => ['amount' => '60000', 'money_account_id' => $world['accounts']['cash']->id],
    ]);

    expect($purchase->payments()->sole()->approval_status->value)->toBe('pending')
        ->and(partyBalance($world['parties']['aslam']))->toBe(['words' => 'We owe', 'amount' => '60000.00']);
});

it('BR-PAY-08 the khata shows a waiting payment greyed and outside the balance; the message says so', function () {
    [, $world] = approvalsWorld();
    $accountant = accountant();

    $this->actingAs($accountant)->post(route('payments.store'), [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-15', 'direction' => 'paid',
        'party_id' => $world['parties']['aslam']->id, 'amount' => '60000', 'money_account_id' => $world['accounts']['cash']->id,
    ])->assertInertiaFlash('success', 'Saved. Waiting for owner approval. Balances will change after approval.');

    $row = PartyKhata::for($world['parties']['aslam'])->rows[0];

    expect([$row->voucher, $row->awaitingApproval, $row->balance->words])->toBe(['PAY-000001', true, 'Settled']);
});

it('page 27 lists waiting payments for payment.approve only', function () {
    [$owner, $world] = approvalsWorld();
    payAs(accountant(), $world['parties']['aslam']->id, $world['accounts']['cash']->id, '60000');

    $this->actingAs(accountant())->get(route('approvals.index'))->assertForbidden();
    $this->actingAs($owner)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->component('approvals/index')->has('payments', 1)->where('payments.0.amount', '60000.00'));
});

it('AT-4 accountant payment above the limit waits for owner approval', function () {
    $case = collect(acceptance('controls')['cases'])->firstWhere('id', 'AT-4');
    freezeToday($case['today']);
    $users = ['owner' => owner(), 'accountant' => accountant()];
    $world = acceptanceWorld($case, $users['owner']);
    $refs = [];

    foreach ($case['steps'] as $step) {
        $by = $users[$step['as'] ?? 'owner'];

        $record = match ($step['type']) {
            'purchase' => app(CreatePurchase::class)->handle($by, [
                'idempotency_key' => (string) Str::uuid(), 'date' => $step['date'], 'party_id' => $world['parties'][$step['party']]->id,
                'item_id' => Item::query()->where('name', $step['item'])->value('id'), 'kg' => $step['kg'], 'rate' => $step['rate'],
            ]),
            'payment' => app(CreatePayment::class)->handle($by, [
                'idempotency_key' => (string) Str::uuid(), 'date' => $step['date'], 'direction' => $step['direction'],
                'party_id' => $world['parties'][$step['party']]->id, 'amount' => $step['amount'],
                'money_account_id' => $world['accounts'][$step['money_account']]->id, 'reference_no' => $step['reference_no'] ?? null,
            ]),
            'approve' => app(ApprovePayment::class)->handle($by, $refs[$step['target']]),
        };

        if (isset($step['ref'])) {
            $refs[$step['ref']] = $record;
        }

        $expect = $step['expect'] ?? [];
        $record = $record->fresh();

        if (isset($expect['approval_status'])) {
            expect($record->approval_status->value)->toBe($expect['approval_status']);
        }

        if (isset($expect['journal'])) {
            expect($record->journal_entry_id !== null)->toBe($expect['journal']);
        }

        if (isset($expect['journal_posting_date'])) {
            expect(DB::table('journal_entries')->where('id', $record->journal_entry_id)->value('posting_date'))->toBe($expect['journal_posting_date']);
        }

        foreach ($expect['parties'] ?? [] as $key => $balance) {
            expect(partyBalance($world['parties'][$key]))->toBe($balance);
        }

        foreach ($expect['money_accounts'] ?? [] as $key => $amount) {
            expect($world['accounts'][$key]->balance())->toBe($amount);
        }
    }
});

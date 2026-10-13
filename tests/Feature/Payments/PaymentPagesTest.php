<?php

use App\Domain\Items\Models\Item;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Sales\Actions\CreateSale;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * @return array{User, array{accounts: array<string, mixed>, parties: array<string, mixed>}}
 */
function paymentPagesWorld(): array
{
    $owner = owner();

    return [$owner, acceptanceWorld(acceptance('at1'), $owner)];
}

function quickPayment(User $by, string $direction, int $partyId, int $accountId, string $amount): Payment
{
    return app(CreatePayment::class)->handle($by, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-06', 'direction' => $direction,
        'party_id' => $partyId, 'amount' => $amount, 'money_account_id' => $accountId,
    ]);
}

it('payment list filters by direction', function () {
    [$owner, $world] = paymentPagesWorld();
    $cash = $world['accounts']['cash']->id;
    quickPayment($owner, 'paid', $world['parties']['aslam']->id, $cash, '1000');
    quickPayment($owner, 'paid', $world['parties']['karim']->id, $cash, '500');
    quickPayment($owner, 'received', $world['parties']['abc']->id, $cash, '2500');

    $this->actingAs($owner)->get(route('payments.index', ['direction' => 'paid']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/index')
            ->where('payments.total', 2)
            ->where('totals', ['paid' => '1500.00', 'received' => '0.00']));

    $this->actingAs($owner)->get(route('payments.index', ['direction' => 'received']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.total', 1)
            ->where('payments.data.0.voucher_no', 'RCV-000001')
            ->where('totals', ['paid' => '0.00', 'received' => '2500.00']));

    $this->actingAs($owner)->get(route('payments.index'))
        ->assertInertia(fn (Assert $page) => $page->where('payments.total', 3));
});

it('payment detail shows linked purchase or sale', function () {
    [$owner, $world] = paymentPagesWorld();
    $purchase = app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '800', 'rate' => '50',
        'paid_now' => ['amount' => '40000', 'money_account_id' => $world['accounts']['cash']->id],
    ]);
    $sale = app(CreateSale::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties']['rehman']->id,
        'item_id' => Item::query()->where('code', 'PROTEIN')->value('id'), 'kg' => '200', 'rate' => '205',
        'received_now' => ['amount' => '41000', 'money_account_id' => $world['accounts']['hbl']->id, 'reference_no' => 'AT1-REF-06'],
    ]);

    $this->actingAs($owner)->get(route('payments.show', $purchase->payments()->sole()))
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/show')
            ->where('payment.linked', ['type' => 'purchase', 'id' => $purchase->id, 'voucher_no' => 'PUR-000001'])
            ->where('payment.method', 'Cash'));

    $this->actingAs($owner)->get(route('payments.show', $sale->payments()->sole()))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payment.linked', ['type' => 'sale', 'id' => $sale->id, 'voucher_no' => 'SAL-000001'])
            ->where('payment.method', 'Bank transfer')
            ->where('payment.reference_no', 'AT1-REF-06'));
});

it('page 10 opens with the party pre-selected from the profile; a new payment opens its detail page', function () {
    [$owner, $world] = paymentPagesWorld();

    $this->actingAs($owner)->get(route('payments.create', ['party_id' => $world['parties']['karim']->id]))
        ->assertInertia(fn (Assert $page) => $page->component('payments/create')->where('party.label', 'Karim – Mandian – 03000000002'));

    $this->actingAs($owner)->post(route('payments.store'), [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-06', 'direction' => 'paid',
        'party_id' => $world['parties']['karim']->id, 'amount' => '100', 'money_account_id' => $world['accounts']['cash']->id,
    ])->assertRedirect(route('payments.show', Payment::query()->sole()));
});

it('payment pages need payment.view and payment.create', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('payments.index'))->assertForbidden();
    $this->actingAs($user)->get(route('payments.create'))->assertForbidden();
});

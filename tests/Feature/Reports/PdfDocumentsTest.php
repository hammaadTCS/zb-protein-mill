<?php

use App\Domain\Items\Models\Item;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Actions\CancelPurchase;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Reports\Documents\EntrySlip;
use App\Domain\Reports\Documents\PartyStatement;
use App\Domain\Sales\Models\Sale;
use App\Support\EntryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AcceptanceRunner;

beforeEach(fn () => freezeToday('2026-01-31'));

/** The statement or slip as HTML, the way dompdf receives it. */
function pdfHtml(string $view, array $data): string
{
    return view($view, $data)->render();
}

it('T-14 D-35 Karim statement after AT-1: every voucher, closing Settled, no Rate pending', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();
    $karim = $runner->parties['karim'];

    $html = pdfHtml('pdf.statement', PartyStatement::data($karim, '2026-01-01', '2026-01-31', $runner->owner));

    $vouchers = [
        ...Purchase::query()->where('party_id', $karim->id)->where('status', EntryStatus::Active->value)->pluck('voucher_no')->all(),
        ...Payment::query()->where('party_id', $karim->id)->where('status', EntryStatus::Active->value)
            ->where('approval_status', '!=', ApprovalStatus::Pending->value)->pluck('voucher_no')->all(),
    ];

    expect($vouchers)->toHaveCount(4)
        ->and($html)->toContain('Statement of Account')
        ->and($html)->toContain('Karim')
        ->and($html)->toContain('S-0002')
        ->and($html)->not->toContain('Rate pending')
        ->and($html)->toContain('Generated on')
        ->and($html)->toContain('by '.$runner->owner->name);

    foreach ($vouchers as $voucher) {
        expect($html)->toContain($voucher);
    }

    // The last balance row is the closing balance.
    expect(preg_match('/Closing balance<\/td>\s*<td class="num">([^<]+)</', $html, $closing))->toBe(1)
        ->and(trim($closing[1]))->toBe('Settled');
});

it('T-14 the statement route returns a PDF', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();

    $response = $this->actingAs($runner->accountant)
        ->get(route('parties.statement', ['party' => $runner->parties['karim'], 'from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(substr((string) $response->getContent(), 0, 5))->toBe('%PDF-');
});

it('T-14 slip for AT-1 #1: voucher, kg, rate, amount and Settled, and the PDF route', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();
    $purchase = $runner->records['1'];

    $html = pdfHtml('pdf.slip', EntrySlip::data($purchase));

    // kg follows `kg_decimals` like the screens: 3 in tests (conventions §11), 0 gives "800 kg".
    expect($html)->toContain('Purchase Slip')
        ->and($html)->toContain('PUR-000001')
        ->and($html)->toContain('800.000 kg')
        ->and($html)->toContain('Rs 50.00')
        ->and($html)->toContain('Rs 40,000.00')
        ->and($html)->toContain('Paid now')
        ->and($html)->toContain('Balance: Settled');

    DB::table('settings')->where('key', 'kg_decimals')->update(['value' => '0']);

    expect(pdfHtml('pdf.slip', EntrySlip::data($purchase)))->toContain('800 kg');

    $this->actingAs($runner->accountant)->get(route('purchases.slip', $purchase))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('T-14 the slip never shows our weight', function () {
    $owner = owner();
    $aslam = app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']);
    $purchase = app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $aslam->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '800', 'rate' => '50', 'our_weight_kg' => '793.5',
    ], ['weight_difference']);

    $html = pdfHtml('pdf.slip', EntrySlip::data($purchase));

    expect($html)->toContain('800.000 kg')
        ->and($html)->toContain('Balance: We owe Rs 40,000.00')
        ->and($html)->not->toContain('793')
        ->and($html)->not->toContain('Our weight');
});

it('T-14 a sale slip says Sale Slip and Received now', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();
    $sale = collect($runner->records)->first(fn ($record) => $record instanceof Sale);

    $html = pdfHtml('pdf.slip', EntrySlip::data($sale));

    expect($html)->toContain('Sale Slip')
        ->and($html)->toContain($sale->voucher_no)
        ->and($html)->toContain('Received now');

    $this->actingAs($runner->accountant)->get(route('sales.slip', $sale))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('T-14 spec §13 the statement has the English name only, never the Urdu one', function () {
    $owner = owner();
    $party = app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => 'Aslam', 'name_ur' => 'اسلم', 'mobile' => '03000000001']);

    $html = pdfHtml('pdf.statement', PartyStatement::data($party, '2026-01-01', '2026-01-31', $owner));

    expect($html)->toContain('Aslam')
        ->and($html)->not->toContain('اسلم')
        ->and($html)->toContain('Opening balance')
        ->and($html)->toContain('Settled');
});

it('T-14 spec §13.1 a cancelled entry and an awaiting payment are left out', function () {
    $runner = AcceptanceRunner::load('at1')->setUp()->runAll();
    $aslam = $runner->parties['aslam'];

    $cancelled = app(CreatePurchase::class)->handle($runner->owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-20', 'party_id' => $aslam->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => '100', 'rate' => '50',
    ], ['duplicate_entry', 'unusual_rate']);
    app(CancelPurchase::class)->handle($runner->owner, $cancelled, 'Entered by mistake');
    $awaiting = app(CreatePayment::class)->handle($runner->accountant, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-20', 'direction' => 'paid', 'party_id' => $aslam->id,
        'amount' => '60000', 'money_account_id' => $runner->accounts['hbl']->id, 'reference_no' => 'T14-REF',
    ]);

    expect($awaiting->approval_status)->toBe(ApprovalStatus::Pending);

    $html = pdfHtml('pdf.statement', PartyStatement::data($aslam, '2026-01-01', '2026-01-31', $runner->owner));

    expect($html)->not->toContain($cancelled->voucher_no)
        ->and($html)->not->toContain($awaiting->voucher_no)
        ->and($html)->not->toContain('Awaiting approval')
        ->and($html)->not->toContain('Reversal')
        ->and(preg_match('/Closing balance<\/td>\s*<td class="num">([^<]+)</', $html, $closing))->toBe(1)
        ->and(trim($closing[1]))->toBe('We owe Rs 52,000.00');
});

it('T-14 the statement PDF needs party.view', function () {
    $party = app(CreateParty::class)->handle(owner(), ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']);

    $this->get(route('parties.statement', $party))->assertRedirect(route('login'));
});

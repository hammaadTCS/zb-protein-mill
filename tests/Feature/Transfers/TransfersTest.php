<?php

use App\Domain\Transfers\Actions\CancelTransfer;
use App\Domain\Transfers\Actions\CreateTransfer;
use App\Domain\Transfers\Actions\UpdateTransfer;
use App\Domain\Transfers\Models\Transfer;
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
function transferWorld(): array
{
    $owner = owner();

    return [$owner, acceptanceWorld(acceptance('at1'), $owner)];
}

/**
 * AT-1 #17 as form data.
 *
 * @return array<string, mixed>
 */
function at1Transfer(array $world): array
{
    $entry = collect(acceptance('at1')['entries'])->firstWhere('no', 17);

    return [
        'idempotency_key' => (string) Str::uuid(),
        'date' => $entry['date'],
        'from_account_id' => $world['accounts'][$entry['from']]->id,
        'to_account_id' => $world['accounts'][$entry['to']]->id,
        'amount' => $entry['amount'],
        'reference_no' => $entry['reference_no'],
    ];
}

it('PR-16 AT-1 #17 HBL to Cash 20000 debits cash and credits HBL', function () {
    [$owner, $world] = transferWorld();

    $transfer = app(CreateTransfer::class)->handle($owner, at1Transfer($world));

    expect(journalLines($transfer->journal_entry_id))->toBe([
        ['1010', '20000.00', '0.00', null, null],
        ['1100', '0.00', '20000.00', null, null],
    ])
        ->and($transfer->voucher_no)->toBe('TRF-000001')
        ->and($transfer->reference_no)->toBe('AT1-REF-17');
});

it('BR-TRF-01 the same account is refused', function () {
    [$owner, $world] = transferWorld();

    expect(fn () => app(CreateTransfer::class)->handle($owner, [...at1Transfer($world), 'to_account_id' => $world['accounts']['hbl']->id]))
        ->toThrow(ValidationException::class, 'Choose two different accounts.')
        ->and(Transfer::query()->count())->toBe(0);
});

it('BR-TRF-03 a transfer touches no income or expense account', function () {
    [$owner, $world] = transferWorld();

    $transfer = app(CreateTransfer::class)->handle($owner, at1Transfer($world));

    $types = DB::table('journal_lines')
        ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
        ->where('journal_lines.journal_entry_id', $transfer->journal_entry_id)
        ->pluck('ledger_accounts.type')
        ->unique()
        ->values()
        ->all();

    expect($types)->toBe(['asset']);
});

it('T-18 BR-GEN-11 the same idempotency key twice creates one transfer and one voucher', function () {
    [$owner, $world] = transferWorld();
    $data = at1Transfer($world);

    $first = app(CreateTransfer::class)->handle($owner, $data);
    $second = app(CreateTransfer::class)->handle($owner, $data);

    expect($second->id)->toBe($first->id)
        ->and(Transfer::query()->count())->toBe(1)
        ->and(DB::table('journal_entries')->where('source_type', 'transfer')->count())->toBe(1)
        ->and(DB::table('voucher_sequences')->where('doc_type', 'transfer')->value('next_number'))->toBe(2);
});

it('BR-GEN-10 a transfer out of cash beyond the balance warns', function () {
    [$owner, $world] = transferWorld();
    $data = [...at1Transfer($world), 'from_account_id' => $world['accounts']['cash']->id, 'to_account_id' => $world['accounts']['hbl']->id, 'amount' => '80000.01'];

    expect(fn () => app(CreateTransfer::class)->handle($owner, $data))->toThrow(WarningRequired::class)
        ->and(app(CreateTransfer::class)->handle($owner, [...$data, 'amount' => '80000'])->amount)->toBe('80000.00');
});

it('BR-GEN-03 editing reverses and reposts; cancelling reverses and keeps the row', function () {
    [$owner, $world] = transferWorld();
    $transfer = app(CreateTransfer::class)->handle($owner, at1Transfer($world));
    $transfer->refresh();

    $edited = app(UpdateTransfer::class)->handle($owner, $transfer, [
        'version' => $transfer->updated_at?->toIso8601String(), 'date' => $transfer->entry_date,
        'from_account_id' => $transfer->from_account_id, 'to_account_id' => $transfer->to_account_id,
        'amount' => '25000', 'reference_no' => $transfer->reference_no,
    ]);

    expect(journalLines($edited->journal_entry_id))->toBe([
        ['1010', '25000.00', '0.00', null, null],
        ['1100', '0.00', '25000.00', null, null],
    ])
        ->and(ledgerBalance('1010'))->toBe('105000.00');

    app(CancelTransfer::class)->handle($owner, $edited, 'Moved by mistake');

    expect(Transfer::query()->sole()->status->value)->toBe('cancelled')
        ->and(ledgerBalance('1010'))->toBe('80000.00')
        ->and(ledgerBalance('1100'))->toBe('300000.00');
});

it('pages 15 and 16 list and save transfers for the accountant too', function () {
    [, $world] = transferWorld();
    $accountant = accountant();

    $this->actingAs($accountant)->get(route('transfers.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('transfers/create')->has('money_accounts', 2));

    $this->actingAs($accountant)->post(route('transfers.store'), at1Transfer($world))->assertRedirect(route('transfers.index'));

    $this->actingAs($accountant)->get(route('transfers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transfers/index')
            ->where('transfers.total', 1)
            ->where('transfers.data.0.from', 'HBL')
            ->where('transfers.data.0.to', 'Cash')
            ->where('total', '20000.00'));

    $this->actingAs(User::factory()->create())->get(route('transfers.index'))->assertForbidden();
});

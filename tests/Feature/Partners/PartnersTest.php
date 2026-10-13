<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Partners\Actions\CancelPartnerTransaction;
use App\Domain\Partners\Actions\CreatePartnerTransaction;
use App\Domain\Partners\Actions\UpdatePartnerTransaction;
use App\Domain\Partners\Models\PartnerTransaction;
use App\Domain\Reports\Queries\PartnerStatement;
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
function partnerWorld(): array
{
    $owner = owner();

    return [$owner, acceptanceWorld(acceptance('at1'), $owner)];
}

/**
 * An AT-1 `partner` entry as form data.
 *
 * @return array<string, mixed>
 */
function partnerEntryData(int $no, array $world): array
{
    $entry = collect(acceptance('at1')['entries'])->firstWhere('no', $no);

    return [
        'idempotency_key' => (string) Str::uuid(),
        'date' => $entry['date'],
        'party_id' => $world['parties'][$entry['party']]->id,
        'type' => $entry['partner_type'],
        'amount' => $entry['amount'],
        'money_account_id' => $world['accounts'][$entry['money_account']]->id,
    ];
}

it('PR-14 AT-1 #15 Ali invests 100000 into HBL', function () {
    [$owner, $world] = partnerWorld();

    $entry = app(CreatePartnerTransaction::class)->handle($owner, partnerEntryData(15, $world));

    expect(journalLines($entry->journal_entry_id))->toBe([
        ['1100', '100000.00', '0.00', null, null],
        ['3000', '0.00', '100000.00', 'P-0001', null],
    ])
        ->and($entry->voucher_no)->toBe('PTN-000001')
        ->and($world['parties']['ali']->code)->toBe('P-0001');
});

it('PR-15 AT-1 #16 Usman withdraws 10000 cash', function () {
    [$owner, $world] = partnerWorld();

    $entry = app(CreatePartnerTransaction::class)->handle($owner, partnerEntryData(16, $world));

    expect(journalLines($entry->journal_entry_id))->toBe([
        ['3100', '10000.00', '0.00', 'P-0002', null],
        ['1010', '0.00', '10000.00', null, null],
    ]);
});

it('R-09 after AT-1 openings, #15 and #16 shows invested, withdrawn and net per partner', function () {
    [$owner, $world] = partnerWorld();
    app(CreatePartnerTransaction::class)->handle($owner, partnerEntryData(15, $world));
    app(CreatePartnerTransaction::class)->handle($owner, partnerEntryData(16, $world));
    $expected = acceptance('at1')['expected']['partners'];

    $rows = collect(PartnerStatement::between('2026-01-01', '2026-01-31'))->keyBy('code');

    foreach (['ali', 'usman'] as $key) {
        $row = $rows[$world['parties'][$key]->code];
        expect(['invested' => $row['invested'], 'withdrawn' => $row['withdrawn'], 'net' => $row['net']])->toBe($expected[$key]);
    }

    $this->actingAs($owner)->get(route('partners.statement'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('partners/statement')
            ->where('filters.from', '2026-01-01')
            ->where('rows.0.name', 'Ali')
            ->where('rows.0.net', $expected['ali']['net'])
            ->where('rows.1.withdrawn', $expected['usman']['withdrawn']));
});

it('BR-PTN-03 accountant gets 403 on create and on the statement', function () {
    [, $world] = partnerWorld();
    $accountant = accountant();

    $this->actingAs($accountant)->get(route('partners.create'))->assertForbidden();
    $this->actingAs($accountant)->post(route('partners.store'), partnerEntryData(15, $world))->assertForbidden();
    $this->actingAs($accountant)->get(route('partners.statement'))->assertForbidden();
    $this->actingAs($accountant)->get(route('partners.index'))->assertForbidden();

    expect(PartnerTransaction::query()->count())->toBe(0);
});

it('T-18 BR-GEN-11 the same idempotency key twice creates one partner entry and one voucher', function () {
    [$owner, $world] = partnerWorld();
    $data = partnerEntryData(15, $world);

    $first = app(CreatePartnerTransaction::class)->handle($owner, $data);
    $second = app(CreatePartnerTransaction::class)->handle($owner, $data);

    expect($second->id)->toBe($first->id)
        ->and(PartnerTransaction::query()->count())->toBe(1)
        ->and(DB::table('journal_entries')->where('source_type', 'partner_transaction')->count())->toBe(1)
        ->and(DB::table('voucher_sequences')->where('doc_type', 'partner_transaction')->value('next_number'))->toBe(2);
});

it('BR-PTN-01 the party must be a partner', function () {
    [$owner, $world] = partnerWorld();

    expect(fn () => app(CreatePartnerTransaction::class)->handle($owner, [...partnerEntryData(15, $world), 'party_id' => $world['parties']['aslam']->id]))
        ->toThrow(ValidationException::class, 'Select an active partner.');
});

it('BR-GEN-10 a withdrawal from cash beyond the balance warns', function () {
    [$owner, $world] = partnerWorld();

    expect(fn () => app(CreatePartnerTransaction::class)->handle($owner, [...partnerEntryData(16, $world), 'amount' => '90000']))
        ->toThrow(WarningRequired::class);

    $entry = app(CreatePartnerTransaction::class)->handle($owner, [...partnerEntryData(16, $world), 'amount' => '90000'], ['negative_cash']);

    expect(AuditLog::query()->where('action', 'warning_confirmed')->where('auditable_id', $entry->id)->sole()->new_values['code'])->toBe('negative_cash');
});

it('BR-GEN-03 editing reverses and reposts; cancelling reverses and keeps the row', function () {
    [$owner, $world] = partnerWorld();
    $entry = app(CreatePartnerTransaction::class)->handle($owner, partnerEntryData(15, $world));
    $original = $entry->journal_entry_id;
    $entry->refresh();

    $edited = app(UpdatePartnerTransaction::class)->handle($owner, $entry, [
        'version' => $entry->updated_at?->toIso8601String(), 'date' => $entry->entry_date, 'party_id' => $entry->party_id,
        'type' => 'investment', 'amount' => '120000', 'money_account_id' => $entry->money_account_id,
    ]);

    expect(journalLines(DB::table('journal_entries')->where('reversal_of_id', $original)->value('id')))->toBe([
        ['1100', '0.00', '100000.00', null, null],
        ['3000', '100000.00', '0.00', 'P-0001', null],
    ])
        ->and(journalLines($edited->journal_entry_id))->toBe([
            ['1100', '120000.00', '0.00', null, null],
            ['3000', '0.00', '120000.00', 'P-0001', null],
        ]);

    app(CancelPartnerTransaction::class)->handle($owner, $edited, 'Entered the wrong amount');

    expect(PartnerTransaction::query()->sole()->status->value)->toBe('cancelled')
        ->and(collect(PartnerStatement::between('2026-01-01', '2026-01-31'))->firstWhere('code', 'P-0001')['invested'])->toBe('200000.00');
});

it('page 21 lists partner entries with totals and page 22 saves one', function () {
    [$owner, $world] = partnerWorld();

    $this->actingAs($owner)->post(route('partners.store'), partnerEntryData(15, $world))->assertRedirect(route('partners.index'));
    $this->actingAs($owner)->post(route('partners.store'), partnerEntryData(16, $world))->assertRedirect(route('partners.index'));

    $this->actingAs($owner)->get(route('partners.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('partners/index')
            ->where('entries.total', 2)
            ->where('entries.data.0.voucher_no', 'PTN-000002')
            ->where('entries.data.0.type', 'withdrawal')
            ->where('totals', ['invested' => '100000.00', 'withdrawn' => '10000.00']));

    $this->actingAs($owner)->get(route('partners.create'))
        ->assertInertia(fn (Assert $page) => $page->component('partners/create')->where('today', '2026-01-31'));
});

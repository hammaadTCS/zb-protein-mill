<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Items\Models\Item;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Actions\DeactivateParty;
use App\Domain\Parties\Actions\MergeParties;
use App\Domain\Parties\Models\Party;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Reports\Queries\PartyKhata;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AcceptanceRunner;

beforeEach(fn () => freezeToday('2026-01-31'));

function mergeSupplier(User $owner, string $name, string $mobile): Party
{
    return app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => $name, 'mobile' => $mobile]);
}

function mergePurchase(User $owner, Party $party, string $kg, ?string $rate = '50'): void
{
    app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-10', 'party_id' => $party->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => $kg, 'rate' => $rate,
    ], ['duplicate_entry', 'unusual_rate']);
}

it('AT-6 merge a duplicate supplier', function () {
    AcceptanceRunner::load('controls', 'AT-6')->setUp()->runAll()->assertExpected();
});

it('T-15 BR-PTY-08 merge leaves the source journal lines untouched', function () {
    $owner = owner();
    [$aslam, $duplicate] = [mergeSupplier($owner, 'Aslam', '03000000001'), mergeSupplier($owner, 'Aslam Bhai', '03000000011')];
    mergePurchase($owner, $aslam, '100');
    mergePurchase($owner, $duplicate, '200');
    $before = DB::table('journal_lines')->where('party_id', $duplicate->id)->orderBy('id')->get()->map(fn ($l) => (array) $l)->all();
    $journals = DB::table('journal_entries')->count();

    app(MergeParties::class)->handle($owner, $duplicate, $aslam);

    $after = DB::table('journal_lines')->where('party_id', $duplicate->id)->orderBy('id')->get()->map(fn ($l) => (array) $l)->all();

    expect(array_slice($after, 0, count($before)))->toBe($before)
        ->and(count($after))->toBe(count($before) + 1)
        ->and(DB::table('journal_entries')->count())->toBe($journals + 1)
        ->and(partyBalance($duplicate->refresh()))->toBe(['words' => 'Settled', 'amount' => '0.00'])
        ->and(partyBalance($aslam))->toBe(['words' => 'We owe', 'amount' => '15000.00'])
        ->and($duplicate->status->value)->toBe('inactive')
        ->and($duplicate->merged_into_id)->toBe($aslam->id)
        ->and(AuditLog::query()->where('action', 'merged')->pluck('auditable_id')->sort()->values()->all())->toBe(collect([$aslam->id, $duplicate->id])->sort()->values()->all());
});

it('BR-PTY-08 merging a supplier into a buyer is refused', function () {
    $owner = owner();
    $supplier = mergeSupplier($owner, 'Aslam', '03000000001');
    $buyer = app(CreateParty::class)->handle($owner, ['type' => 'buyer', 'name' => 'ABC Feeds', 'mobile' => '03000000003']);

    expect(fn () => app(MergeParties::class)->handle($owner, $supplier, $buyer))
        ->toThrow(ValidationException::class, 'Only parties of the same type can be merged.')
        ->and($supplier->refresh()->status->value)->toBe('active');
});

it('BR-PTY-08 refuses the same party, an inactive party, and a source with a pending rate', function () {
    $owner = owner();
    [$aslam, $duplicate] = [mergeSupplier($owner, 'Aslam', '03000000001'), mergeSupplier($owner, 'Aslam Bhai', '03000000011')];

    expect(fn () => app(MergeParties::class)->handle($owner, $aslam, $aslam))->toThrow(ValidationException::class, 'Pick two different parties.');

    mergePurchase($owner, $duplicate, '200', null);

    expect(fn () => app(MergeParties::class)->handle($owner, $duplicate, $aslam))
        ->toThrow(ValidationException::class, 'Aslam Bhai has rate-pending entries or payments awaiting approval. Finish them first.');

    app(DeactivateParty::class)->handle($owner, $aslam);

    expect(fn () => app(MergeParties::class)->handle($owner, $duplicate, $aslam))->toThrow(ValidationException::class, 'Both parties must be active.');
});

it('a settled duplicate merges without a journal', function () {
    $owner = owner();
    [$aslam, $duplicate] = [mergeSupplier($owner, 'Aslam', '03000000001'), mergeSupplier($owner, 'Aslam Bhai', '03000000011')];

    app(MergeParties::class)->handle($owner, $duplicate, $aslam);

    expect(DB::table('journal_entries')->where('source_type', 'party_merge')->count())->toBe(0)
        ->and($duplicate->refresh()->merged_into_id)->toBe($aslam->id);
});

it('page 20 and the khata: preview, merge, the merge rows and Merged into', function () {
    $owner = owner();
    [$aslam, $duplicate] = [mergeSupplier($owner, 'Aslam', '03000000001'), mergeSupplier($owner, 'Aslam Bhai', '03000000011')];
    mergePurchase($owner, $aslam, '100');
    mergePurchase($owner, $duplicate, '200');

    $this->actingAs($owner)->get(route('parties.merge', ['source_id' => $duplicate->id, 'target_id' => $aslam->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('parties/merge')
            ->where('source.balance', ['words' => 'We owe', 'amount' => '10000.00'])
            ->where('target.balance', ['words' => 'We owe', 'amount' => '5000.00'])
            ->where('result', ['words' => 'We owe', 'amount' => '15000.00']));

    $this->actingAs(accountant())->post(route('parties.merge.store'), ['source_id' => $duplicate->id, 'target_id' => $aslam->id])->assertForbidden();

    $this->actingAs($owner)->post(route('parties.merge.store'), ['source_id' => $duplicate->id, 'target_id' => $aslam->id])
        ->assertRedirect(route('parties.show', $aslam));

    $targetRow = collect(PartyKhata::for($aslam->refresh())->rows)->last();
    $sourceRow = collect(PartyKhata::for($duplicate->refresh())->rows)->last();

    expect([$targetRow->description, $targetRow->route, $targetRow->increase])->toBe(['Balance moved from Aslam Bhai ('.$duplicate->code.')', route('parties.show', $duplicate->id, false), '10000.00'])
        ->and([$sourceRow->description, $sourceRow->route, $sourceRow->decrease, $sourceRow->balance->words])->toBe(['Balance moved to Aslam ('.$aslam->code.')', route('parties.show', $aslam->id, false), '10000.00', 'Settled']);

    $this->actingAs($owner)->get(route('parties.show', $duplicate))
        ->assertInertia(fn (Assert $page) => $page->where('party.merged_into', ['id' => $aslam->id, 'code' => $aslam->code, 'name' => 'Aslam']));
});

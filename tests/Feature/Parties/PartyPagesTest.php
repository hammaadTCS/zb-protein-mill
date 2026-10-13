<?php

use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Actions\DeactivateParty;
use App\Domain\Parties\Models\Party;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $fields
 */
function makeParty(User $by, string $type, string $name, string $mobile, array $fields = []): Party
{
    return app(CreateParty::class)->handle($by, ['type' => $type, 'name' => $name, 'mobile' => $mobile, ...$fields]);
}

it('BR-PTY-09 search returns label Name – Area – Mobile and excludes inactive parties', function () {
    $owner = owner();
    makeParty($owner, 'supplier', 'Aslam', '03000000001', ['area' => 'Sadar']);
    makeParty($owner, 'supplier', 'Asif', '03000000012'); // no area
    $gone = makeParty($owner, 'supplier', 'Asad', '03000000013', ['area' => 'Sadar']);
    makeParty($owner, 'buyer', 'Asim Feeds', '03000000014');
    app(DeactivateParty::class)->handle($owner, $gone);

    $response = $this->actingAs($owner)
        ->getJson(route('parties.search', ['q' => 'as', 'types' => ['supplier']]))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('label')->all())->toBe([
        'Asif – 03000000012',
        'Aslam – Sadar – 03000000001',
    ]);
});

it('search by mobile digits finds the party', function () {
    $owner = owner();
    makeParty($owner, 'supplier', 'Aslam', '03000000001', ['area' => 'Sadar']);
    makeParty($owner, 'supplier', 'Karim', '03000000002', ['area' => 'Mandian']);

    $response = $this->actingAs($owner)->getJson(route('parties.search', ['q' => '0300-0000002']))->assertOk();

    expect($response->json('data'))->toBe([
        ['id' => Party::query()->where('name', 'Karim')->value('id'), 'code' => 'S-0002', 'type' => 'supplier', 'label' => 'Karim – Mandian – 03000000002'],
    ]);
});

it('BR-PTY-09 search finds by Urdu name and code, and returns at most 20', function () {
    $owner = owner();
    makeParty($owner, 'supplier', 'Aslam', '03000000001', ['name_ur' => 'اسلم']);

    foreach (range(1, 25) as $n) {
        makeParty($owner, 'buyer', "Buyer {$n}", '0310000'.str_pad((string) $n, 4, '0', STR_PAD_LEFT));
    }

    expect($this->actingAs($owner)->getJson(route('parties.search', ['q' => 'اسلم']))->json('data.0.code'))->toBe('S-0001')
        ->and($this->actingAs($owner)->getJson(route('parties.search', ['q' => 'b-0003']))->json('data.0.label'))->toBe('Buyer 3 – 03100000003')
        ->and($this->actingAs($owner)->getJson(route('parties.search', ['types' => ['buyer']]))->json('data'))->toHaveCount(20);
});

it('search needs party.view', function () {
    $user = User::factory()->create(); // no role, no permission

    $this->actingAs($user)->getJson(route('parties.search'))->assertForbidden();
});

it('page 17 lists parties with balance words, filters and 50 rows per page', function () {
    $owner = owner();
    makeParty($owner, 'supplier', 'Aslam', '03000000001', ['area' => 'Sadar']);
    makeParty($owner, 'buyer', 'ABC Feeds', '03000000004');

    $this->actingAs($owner)->get(route('parties.index', ['type' => 'buyer']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('parties/index')
            ->where('parties.total', 1)
            ->where('parties.data.0.name', 'ABC Feeds')
            ->where('parties.data.0.balance', ['words' => 'Settled', 'amount' => '0.00', 'side' => 'zero', 'pending_kg' => '0.000']));

    foreach (range(1, 55) as $n) {
        makeParty($owner, 'other', "Shop {$n}", '0320000'.str_pad((string) $n, 4, '0', STR_PAD_LEFT));
    }

    $this->actingAs($owner)->get(route('parties.index'))
        ->assertInertia(fn (Assert $page) => $page->has('parties.data', 50)->where('parties.last_page', 2));
});

it('page 18 adds a party; a duplicate mobile answers 422 with the warning until confirmed', function () {
    $owner = owner();
    makeParty($owner, 'supplier', 'Aslam', '03000000001');

    $this->actingAs($owner)
        ->post(route('parties.store'), ['type' => 'buyer', 'name' => 'Aslam Feeds', 'mobile' => '0300 0000001'])
        ->assertStatus(422)
        ->assertJsonPath('warning.code', 'duplicate_party')
        ->assertJsonPath('warning.message', 'A party with this mobile already exists: Aslam (S-0001).');

    $this->actingAs($owner)
        ->post(route('parties.store'), ['type' => 'buyer', 'name' => 'Aslam Feeds', 'mobile' => '0300 0000001', 'confirmed_warnings' => ['duplicate_party']])
        ->assertRedirect(route('parties.index'))
        ->assertInertiaFlash('success', 'Saved. B-0001');

    expect(Party::query()->where('code', 'B-0001')->value('mobile'))->toBe('03000000001');
});

it('page 18 edits a party and the accountant may add parties', function () {
    $accountant = accountant();

    $this->actingAs($accountant)
        ->post(route('parties.store'), ['type' => 'supplier', 'name' => 'Karim', 'mobile' => '03000000002', 'area' => 'Mandian'])
        ->assertRedirect(route('parties.index'));

    $party = Party::query()->sole();

    $this->actingAs($accountant)->get(route('parties.edit', $party))
        ->assertInertia(fn (Assert $page) => $page->component('parties/form')->where('party.name', 'Karim')->where('party.has_entries', false));

    $this->actingAs($accountant)
        ->put(route('parties.update', $party), ['type' => 'supplier', 'name' => 'Karim Bhai', 'mobile' => '03000000002'])
        ->assertRedirect(route('parties.index'));

    expect($party->refresh()->name)->toBe('Karim Bhai')
        ->and($party->area)->toBeNull()
        ->and($party->needs_review)->toBeTrue();
});

it('a user without party.create cannot open page 18', function () {
    $this->actingAs(User::factory()->create())->get(route('parties.create'))->assertForbidden();
});

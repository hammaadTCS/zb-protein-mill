<?php

use App\Domain\Companies\Models\Company;
use App\Domain\Items\Models\Item;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

/** Tables whose rows open from a link (spec §9.1, BR-SEC-19). */
const LINKED_TABLES = [
    'parties', 'purchases', 'sales', 'payments', 'expenses', 'transfers', 'partner_transactions',
    'adjustments', 'attachments', 'money_accounts', 'items', 'expense_categories', 'users',
];

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * The AT-1 world and two purchases, one for Aslam and one for Karim.
 *
 * @return array{User, array<string, mixed>, Purchase, Purchase}
 */
function publicIdWorld(): array
{
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);
    $buy = fn (string $party, string $kg) => app(CreatePurchase::class)->handle($owner, [
        'idempotency_key' => (string) Str::uuid(), 'date' => '2026-01-05', 'party_id' => $world['parties'][$party]->id,
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'), 'kg' => $kg, 'rate' => '50',
    ], ['duplicate_entry', 'unusual_rate']);

    return [$owner, $world, $buy('aslam', '800'), $buy('karim', '400')];
}

it('BR-SEC-19 every linked table has a public id the database fills when a row is saved without one', function () {
    foreach (LINKED_TABLES as $table) {
        $column = DB::selectOne(
            'SELECT is_nullable, column_default FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
            [$table, 'public_id'],
        );

        expect($column)->not->toBeNull($table)
            ->and($column->is_nullable)->toBe('NO', $table)
            ->and($column->column_default)->toBe('gen_random_uuid()', $table);
    }
});

it('BR-SEC-19 a new record gets a random version 4 public id, and links use it', function () {
    [$owner, , $first, $second] = publicIdWorld();

    expect($first->public_id)->toMatch(UUID_V4)
        ->and($second->public_id)->toMatch(UUID_V4)
        ->and($first->public_id)->not->toBe($second->public_id)
        ->and(route('purchases.show', $first, false))->toBe("/purchases/{$first->public_id}");

    $this->actingAs($owner)->get("/purchases/{$first->public_id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('purchase.public_id', $first->public_id));
});

it('BR-SEC-19 a record URL with the internal number or a malformed id is not found', function () {
    [$owner, , $purchase] = publicIdWorld();

    $this->actingAs($owner)->get("/purchases/{$purchase->id}")->assertNotFound();
    $this->actingAs($owner)->get('/purchases/not-a-uuid')->assertNotFound();
    $this->actingAs($owner)->get('/purchases/'.Str::uuid())->assertNotFound();
    $this->actingAs($owner)->get("/purchases/{$purchase->id}/slip.pdf")->assertNotFound();
});

it('BR-SEC-19 BR-GEN-01 another company cannot open a record by its public id', function () {
    [, , $purchase] = publicIdWorld();
    $other = Company::query()->create(['name' => 'Other Mill']);
    $stranger = User::factory()->create(['company_id' => $other->id])->assignRole(RolesAndPermissionsSeeder::OWNER);

    $this->actingAs($stranger)->get("/purchases/{$purchase->public_id}")->assertNotFound();
});

it('BR-SEC-19 a list filter takes the public id, shows it back, and refuses the internal number', function () {
    [$owner, $world] = publicIdWorld();
    $karim = $world['parties']['karim'];

    $this->actingAs($owner)->get(route('purchases.index', ['party_id' => $karim->public_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.party_id', $karim->public_id)
            ->where('party.public_id', $karim->public_id)
            ->has('purchases.data', 1)
            ->where('purchases.data.0.party', 'Karim'));

    $this->actingAs($owner)->get(route('purchases.index', ['party_id' => $karim->id]))->assertNotFound();
});

it('BR-SEC-19 links built on the server use the public id', function () {
    [$owner, $world, $purchase] = publicIdWorld();
    $aslam = $world['parties']['aslam'];

    $this->actingAs($owner)->get(route('parties.show', $aslam))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('khata.rows', fn ($rows) => collect($rows)->contains('route', "/purchases/{$purchase->public_id}")));

    $this->actingAs($owner)->get(route('audit-log.index', ['type' => 'purchase']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('logs.data', fn ($logs) => collect($logs)->contains(
            fn ($log) => ($log['record']['url'] ?? null) === "/purchases/{$purchase->public_id}",
        )));
});

it('BR-SEC-19 no page data link or record URL carries an internal number', function () {
    [$owner, $world, $purchase] = publicIdWorld();
    $pages = [
        route('purchases.index'), route('purchases.show', $purchase), route('parties.index'),
        route('parties.show', $world['parties']['aslam']), route('accounts.index'), route('audit-log.index'),
    ];

    foreach ($pages as $url) {
        $html = (string) $this->actingAs($owner)->get($url)->assertOk()->getContent();

        expect(preg_match_all('#"/(purchases|sales|payments|expenses|parties|accounts|transfers|partners|attachments)/(\d+)[/"?]#', $html))
            ->toBe(0, $url);
    }
});

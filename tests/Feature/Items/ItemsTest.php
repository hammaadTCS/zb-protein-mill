<?php

use App\Domain\Items\Actions\CreateItem;
use App\Domain\Items\Actions\DeactivateItem;
use App\Domain\Items\Actions\UpdateItem;
use App\Domain\Items\Models\Item;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\NormalSide;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function itemNamed(string $name): Item
{
    return Item::query()->where('name', $name)->sole();
}

it('BR-ITM-01 four items seeded with linked accounts', function () {
    $items = Item::query()->with(['purchaseAccount', 'salesAccount'])->orderBy('id')->get()
        ->map(fn (Item $i) => [$i->code, $i->name, $i->type->value, $i->unit, $i->purchaseAccount?->code, $i->salesAccount?->code, $i->is_system])
        ->all();

    expect($items)->toBe([
        ['WASTE', 'Waste', 'raw_material', 'kg', '5000', null, true],
        ['WOOD', 'Wood', 'fuel', 'kg', '5010', null, true],
        ['PROTEIN', 'Protein product', 'product', 'kg', null, '4000', true],
        ['OILFAT', 'Oil/Fat', 'product', 'kg', null, '4010', true],
    ]);
});

it('BR-OPS-11 running SystemDataSeeder twice creates no duplicate item', function () {
    itemNamed('Waste')->update(['is_active' => false]);

    $this->seed(SystemDataSeeder::class);

    expect(Item::query()->count())->toBe(4)
        ->and(itemNamed('Waste')->is_active)->toBeFalse(); // an existing item is never changed
});

it('BR-ITM-02 a new raw material item gets account 5020', function () {
    $item = app(CreateItem::class)->handle(owner(), ['code' => 'FEATH', 'name' => 'Feathers', 'type' => 'raw_material']);
    $account = $item->purchaseAccount;

    expect($account?->code)->toBe('5020')
        ->and($account?->name)->toBe('Purchases – Feathers')
        ->and($account?->type)->toBe(AccountType::Expense)
        ->and($account?->normal_side)->toBe(NormalSide::Debit)
        ->and($item->sales_ledger_account_id)->toBeNull();
});

it('BR-ITM-02 a new product gets a sales account from 4020', function () {
    $item = app(CreateItem::class)->handle(owner(), ['code' => 'MEAL', 'name' => 'Bone meal', 'type' => 'product']);

    expect($item->salesAccount?->code)->toBe('4020')
        ->and($item->salesAccount?->name)->toBe('Sales – Bone meal')
        ->and($item->salesAccount?->type)->toBe(AccountType::Income)
        ->and($item->salesAccount?->normal_side)->toBe(NormalSide::Credit);
});

it('system items cannot be renamed into another type', function () {
    expect(fn () => app(UpdateItem::class)->handle(owner(), itemNamed('Waste'), ['name' => 'Waste', 'type' => 'product']))
        ->toThrow(ValidationException::class, 'System items cannot be changed.');

    expect(itemNamed('Waste')->type->value)->toBe('raw_material');
});

it('BR-ITM-02 a new item is renamed with its ledger account; its type may change before any entry', function () {
    $owner = owner();
    $item = app(CreateItem::class)->handle($owner, ['code' => 'FEATH', 'name' => 'Feathers', 'type' => 'raw_material']);
    $oldAccount = $item->purchaseAccount;

    $item = app(UpdateItem::class)->handle($owner, $item, ['name' => 'Feather meal', 'type' => 'product']);

    expect($item->salesAccount?->name)->toBe('Sales – Feather meal')
        ->and($item->salesAccount?->code)->toBe('4020')
        ->and($item->purchase_ledger_account_id)->toBeNull()
        ->and($oldAccount?->refresh()->is_active)->toBeFalse();
});

it('BR-GEN-08 a deactivated item is not active any more', function () {
    app(DeactivateItem::class)->handle(owner(), itemNamed('Wood'));

    expect(Item::query()->active()->orderBy('id')->pluck('name')->all())->toBe(['Waste', 'Protein product', 'Oil/Fat']);
});

it('page 45 lists the items for the owner; the accountant is refused', function () {
    $this->actingAs(owner())->get(route('items.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('items/index')
            ->has('items', 4)
            ->where('items.0.code', 'WASTE')
            ->where('items.0.account_code', '5000'));

    $this->actingAs(accountant())->get(route('items.index'))->assertForbidden();
    $this->actingAs(accountant())
        ->post(route('items.store'), ['code' => 'X', 'name' => 'X', 'type' => 'fuel'])
        ->assertForbidden();
});

it('the owner adds an item from page 45; code and name must be unique', function () {
    $owner = owner();

    $this->actingAs($owner)
        ->post(route('items.store'), ['code' => 'feath', 'name' => 'Feathers', 'type' => 'raw_material'])
        ->assertRedirect(route('items.index'));

    expect(itemNamed('Feathers')->code)->toBe('FEATH');

    $this->actingAs($owner)
        ->post(route('items.store'), ['code' => 'WASTE', 'name' => 'Waste', 'type' => 'raw_material'])
        ->assertSessionHasErrors(['code', 'name']);
});

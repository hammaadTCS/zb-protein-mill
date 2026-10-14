<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SystemDataSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Spec §7, every permission key. */
const SPEC_7_ALL_PERMISSIONS = [
    'party.view', 'party.create', 'party.update', 'party.merge', 'party.review',
    'purchase.view', 'purchase.create', 'purchase.update', 'purchase.cancel',
    'sale.view', 'sale.create', 'sale.update', 'sale.cancel',
    'adjustment.view', 'adjustment.create', 'adjustment.cancel',
    'payment.view', 'payment.create', 'payment.update', 'payment.cancel', 'payment.approve',
    'expense.view', 'expense.create', 'expense.update', 'expense.cancel',
    'transfer.view', 'transfer.create', 'transfer.update', 'transfer.cancel',
    'partner.view', 'partner.manage', 'account.view', 'cashcount.create',
    'period.close', 'period.reopen', 'report.view', 'insights.view', 'audit.view',
    'settings.manage', 'opening.manage', 'users.manage', 'backup.view',
    'dashboard.balances',
];

/** Spec §7, the "No" column for the Accountant. */
const SPEC_7_OWNER_ONLY = [
    'party.merge', 'party.review', 'payment.approve', 'partner.view', 'partner.manage',
    'period.close', 'period.reopen', 'insights.view', 'audit.view', 'settings.manage',
    'opening.manage', 'users.manage', 'backup.view', 'dashboard.balances',
];

it('spec §7 owner has every permission', function () {
    expect(owner()->getAllPermissions()->pluck('name')->all())
        ->toEqualCanonicalizing(SPEC_7_ALL_PERMISSIONS);
});

it('spec §7 accountant has exactly the accountant list', function () {
    expect(accountant()->getAllPermissions()->pluck('name')->all())
        ->toEqualCanonicalizing(array_values(array_diff(SPEC_7_ALL_PERMISSIONS, SPEC_7_OWNER_ONLY)));
});

it('BR-OPS-11 running SystemDataSeeder again restores a permission removed from a role', function () {
    Role::findByName('accountant')->revokePermissionTo('purchase.create');
    Role::findByName('owner')->givePermissionTo(Permission::findByName('party.view'));

    $this->seed(SystemDataSeeder::class);
    $this->seed(SystemDataSeeder::class);

    expect(Role::findByName('accountant')->hasPermissionTo('purchase.create'))->toBeTrue()
        ->and(Permission::query()->count())->toBe(count(SPEC_7_ALL_PERMISSIONS))
        ->and(Role::query()->count())->toBe(2);
});

it('BR-OPS-11 running SystemDataSeeder takes away a permission the accountant must not have', function () {
    Role::findByName('accountant')->givePermissionTo('payment.approve');

    $this->seed(SystemDataSeeder::class);

    expect(Role::findByName('accountant')->hasPermissionTo('payment.approve'))->toBeFalse();
});

it('local DatabaseSeeder gives test@example.com the owner role', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe(['owner']);
});

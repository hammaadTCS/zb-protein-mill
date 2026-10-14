<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec §7: the Owner and Accountant roles with exactly these permissions.
 * BR-OPS-11: runs on every deploy (inside SystemDataSeeder). It adds missing
 * permissions and roles, then sets each role to exactly its list, so a release
 * that changes §7 corrects every server on its next deploy.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
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
        'dashboard.balances', // BR-DSH-01 (T9.3)
    ];

    /** Spec §7: what the Accountant may never do. */
    public const OWNER_ONLY = [
        'party.merge', 'party.review', 'payment.approve', 'partner.view', 'partner.manage',
        'period.close', 'period.reopen', 'insights.view', 'audit.view', 'settings.manage',
        'opening.manage', 'users.manage', 'backup.view', 'dashboard.balances',
    ];

    public const OWNER = 'owner';

    public const ACCOUNTANT = 'accountant';

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(self::OWNER, 'web')->syncPermissions(self::PERMISSIONS);
        Role::findOrCreate(self::ACCOUNTANT, 'web')->syncPermissions(self::accountantPermissions());

        $registrar->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    public static function accountantPermissions(): array
    {
        return array_values(array_diff(self::PERMISSIONS, self::OWNER_ONLY));
    }
}

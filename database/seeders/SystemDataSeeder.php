<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * BR-OPS-11: the system data every server needs. zb-deploy-release runs it on every
 * deploy, right after the migrations. It only adds rows that are missing: it never
 * changes saved data, never creates users and never creates test data, so it is safe
 * to run any number of times.
 */
class SystemDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            SettingsDefinitionSeeder::class,
            RolesAndPermissionsSeeder::class,
            SystemLedgerAccountsSeeder::class,
            ItemsSeeder::class,
        ]);
    }
}

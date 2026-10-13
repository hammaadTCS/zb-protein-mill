<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemDataSeeder;
use Database\Seeders\TestSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // conventions §11: system data, then the test settings, in every feature test.
        $this->seed(SystemDataSeeder::class);
        $this->seed(TestSettingsSeeder::class);
    })
    ->in('Feature');

/** A user with the Owner role in the seeded company (spec §7). */
function owner(): User
{
    return User::factory()->create()->assignRole(RolesAndPermissionsSeeder::OWNER);
}

/** A user with the Accountant role in the seeded company (spec §7). */
function accountant(): User
{
    return User::factory()->create()->assignRole(RolesAndPermissionsSeeder::ACCOUNTANT);
}

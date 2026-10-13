<?php

use App\Domain\Ledger\PostingService;
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

/**
 * Runs the callback with PostingService's private posting flag set, so a test can
 * write journal rows the way only PostingService may (T1.5 database tests).
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function withPostingFlag(Closure $callback): mixed
{
    $flag = new ReflectionProperty(PostingService::class, 'posting');
    $flag->setValue(null, true);

    try {
        return $callback();
    } finally {
        $flag->setValue(null, false);
    }
}

<?php

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Ledger\PostingService;
use App\Domain\Parties\Models\Party;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Today;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemDataSeeder;
use Database\Seeders\TestSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

/** conventions §11: freeze "today" (Asia/Karachi) at noon of the given date. */
function freezeToday(string $ymd): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($ymd.' 12:00:00', Today::TIMEZONE));
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse($ymd.' 12:00:00', Today::TIMEZONE));
}

/** T1.9: mark a month (YYYY-MM) as closed directly, until month-end close exists (T7.1). */
function closeMonthForTest(string $yyyymm): void
{
    [$year, $month] = array_map('intval', explode('-', $yyyymm));

    DB::table('periods')->insert([
        'company_id' => CurrentCompany::id(),
        'year' => $year,
        'month' => $month,
        'status' => 'closed',
        'closed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** conventions §11: ledger balance of an account code (debit minus credit), optionally one party's. */
function ledgerBalance(string $code, ?Party $party = null): string
{
    return LedgerBalance::byCode($code, null, $party?->id);
}

/**
 * conventions §11: a party's balance in words.
 *
 * @return array{words: string, amount: string}
 */
function partyBalance(Party $party): array
{
    $balance = $party->balance();

    return ['words' => $balance->words, 'amount' => $balance->amount];
}

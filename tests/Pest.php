<?php

use App\Domain\Checks\Actions\CreateCashCount;
use App\Domain\Ledger\LedgerBalance;
use App\Domain\Ledger\PostingService;
use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\OpeningBalances\Actions\SetOpeningBalance;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Models\Party;
use App\Domain\Periods\Actions\RecordBankStatement;
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
    ->in('Feature', 'Acceptance');

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

/**
 * The lines of one journal as [account code, debit, credit, party code, item name],
 * in line order: for exact journal assertions (CLAUDE.md "assert exact journal lines").
 *
 * @return list<array{string, string, string, string|null, string|null}>
 */
function journalLines(?int $journalEntryId): array
{
    return DB::table('journal_lines')
        ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
        ->leftJoin('parties', 'parties.id', '=', 'journal_lines.party_id')
        ->leftJoin('items', 'items.id', '=', 'journal_lines.item_id')
        ->where('journal_lines.journal_entry_id', $journalEntryId)
        ->orderBy('journal_lines.line_no')
        ->get(['ledger_accounts.code', 'journal_lines.debit', 'journal_lines.credit', 'parties.code as party', 'items.name as item'])
        ->map(fn (object $l): array => [$l->code, $l->debit, $l->credit, $l->party, $l->item])
        ->all();
}

/**
 * The acceptance data of spec §17 (docs/acceptance). Tests read numbers from here;
 * they never copy them (CLAUDE.md).
 *
 * @return array<string, mixed>
 */
function acceptance(string $file): array
{
    /** @var array<string, mixed> $data */
    $data = json_decode((string) file_get_contents(base_path("docs/acceptance/{$file}.json")), true, flags: JSON_THROW_ON_ERROR);

    return $data;
}

/**
 * The money accounts, parties and opening balances of an acceptance file, created
 * through the real Actions (docs/acceptance/README.md, runner step 2).
 *
 * @param  array<string, mixed>  $data
 * @return array{accounts: array<string, MoneyAccount>, parties: array<string, Party>}
 */
function acceptanceWorld(array $data, User $owner): array
{
    $accounts = [];
    $parties = [];

    foreach ($data['money_accounts'] ?? [] as $account) {
        $accounts[$account['key']] = app(CreateMoneyAccount::class)->handle($owner, $account);
    }

    foreach ($data['parties'] ?? [] as $party) {
        $parties[$party['key']] = app(CreateParty::class)->handle($owner, $party);
    }

    foreach ($data['opening_balances'] ?? [] as $opening) {
        app(SetOpeningBalance::class)->handle($owner, isset($opening['money_account'])
            ? ['money_account_id' => $accounts[$opening['money_account']]->id, 'amount' => $opening['amount']]
            : ['party_id' => $parties[$opening['party']]->id, 'side' => $opening['side'] ?? null, 'amount' => $opening['amount']]);
    }

    return ['accounts' => $accounts, 'parties' => $parties];
}

/**
 * The AT-1 accounts with their openings, and February 2nd as today, so January has ended.
 *
 * @return array{User, array<string, MoneyAccount>, array<string, mixed>}
 */
function monthEndWorld(): array
{
    freezeToday('2026-02-02');
    $owner = owner();
    $world = acceptanceWorld(acceptance('at1'), $owner);

    return [$owner, $world['accounts'], $world['parties']];
}

/**
 * Bank statement and cash count for January that match the ledger.
 *
 * @param  array<string, MoneyAccount>  $accounts
 */
function matchJanuary(User $owner, array $accounts): void
{
    app(RecordBankStatement::class)->handle($owner, '2026-01', $accounts['hbl'], $accounts['hbl']->balance('2026-01-31'));
    app(CreateCashCount::class)->handle($owner, $accounts['cash'], '2026-01-31', $accounts['cash']->balance('2026-01-31'));
}

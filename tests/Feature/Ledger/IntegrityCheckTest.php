<?php

use App\Domain\Ledger\IntegrityCheck;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\Support\AcceptanceRunner;

/**
 * T-20, BR-OPS-08. Faults are planted inside the test's own transaction (rolled back
 * after it). MariaDB cannot disable a trigger inside that transaction (DDL commits),
 * so faults are planted the way the rules allow: INSERT only, never UPDATE or DELETE.
 */
beforeEach(function () {
    $this->verifyFile = storage_path('app/zb-verify.json');
    $this->previousVerifyFile = is_file($this->verifyFile) ? file_get_contents($this->verifyFile) : null;
});

afterEach(function () {
    // Put back whatever a local run had written before this test.
    $this->previousVerifyFile === null ? @unlink($this->verifyFile) : file_put_contents($this->verifyFile, $this->previousVerifyFile);
});

/**
 * @return array<string, bool>
 */
function integrityResults(): array
{
    return collect(IntegrityCheck::run())->mapWithKeys(fn (array $r): array => [$r['check'] => $r['ok']])->all();
}

/** The checks that fail, with their IDs. */
function failingIntegrity(): array
{
    return collect(IntegrityCheck::run())->reject(fn (array $r): bool => $r['ok'])->mapWithKeys(fn (array $r): array => [$r['check'] => $r['ids']])->all();
}

it('T-20 after AT-1 every check is OK and the file says ok', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();

    $this->artisan('zb:verify')
        ->expectsOutput('OK journals_balance')
        ->expectsOutput('OK seals')
        ->expectsOutput('OK trial_balance')
        ->expectsOutput('OK voucher_numbers')
        ->expectsOutput('OK control_account_parties')
        ->expectsOutput('OK entry_journals')
        ->assertExitCode(0);

    $file = (string) file_get_contents($this->verifyFile);

    expect($file)->toContain('"result":"ok"')
        ->and(json_decode($file, true)['failed_checks'])->toBe([]);
});

it('T-20 an unbalanced journal fails journals_balance and seals', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();
    [$first, $second] = DB::table('journal_entries')->orderBy('id')->limit(2)->pluck('id')->all();
    $account = DB::table('ledger_accounts')->where('code', '6900')->value('id');
    $company = DB::table('companies')->value('id');

    // MariaDB checks the balance when the seal row is written, not on every line insert, so
    // the extra lines are planted directly: one extra debit on the first journal and one extra
    // credit on the second. Both are unbalanced, the totals still agree. The planted lines also
    // leave each entry differing from its seal row, so the seal re-sum (BR-LED-11) reports them.
    DB::table('journal_lines')->insert([
        ['company_id' => $company, 'journal_entry_id' => $first, 'line_no' => 90, 'ledger_account_id' => $account, 'debit' => '1.00', 'credit' => '0.00'],
        ['company_id' => $company, 'journal_entry_id' => $second, 'line_no' => 90, 'ledger_account_id' => $account, 'debit' => '0.00', 'credit' => '1.00'],
    ]);

    expect(failingIntegrity())->toBe([
        'journals_balance' => ["journal {$first}", "journal {$second}"],
        'seals' => ["journal {$first}", "journal {$second}"],
    ]);

    $this->artisan('zb:verify')
        ->expectsOutput("FAILED journals_balance: journal {$first}, journal {$second}")
        ->expectsOutput("FAILED seals: journal {$first}, journal {$second}")
        ->assertExitCode(1);

    expect((string) file_get_contents($this->verifyFile))->toContain('"result":"failed"')
        ->and(json_decode((string) file_get_contents($this->verifyFile), true)['failed_checks'])->toBe(['journals_balance', 'seals']);
});

it('T-20 a balanced pair of lines added after sealing fails seals only', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();
    // BR-LED-11: the seal row records the entry's line count and its two totals when the entry
    // was saved. A change that keeps the entry balanced — the case journals_balance cannot see —
    // still leaves the entry differing from its seal, which is what the daily re-sum is for.
    $entry = DB::table('journal_lines')->orderBy('id')->value('journal_entry_id');
    $account = DB::table('ledger_accounts')->where('code', '6900')->value('id');
    $company = DB::table('companies')->value('id');

    DB::table('journal_lines')->insert([
        ['company_id' => $company, 'journal_entry_id' => $entry, 'line_no' => 90, 'ledger_account_id' => $account, 'debit' => '1.00', 'credit' => '0.00'],
        ['company_id' => $company, 'journal_entry_id' => $entry, 'line_no' => 91, 'ledger_account_id' => $account, 'debit' => '0.00', 'credit' => '1.00'],
    ]);

    expect(failingIntegrity())->toBe(['seals' => ["journal {$entry}"]]);
});

it('T-20 a voucher gap fails only voucher_numbers', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();
    $next = (int) DB::table('voucher_sequences')->where('doc_type', 'purchase')->value('next_number');
    DB::table('voucher_sequences')->where('doc_type', 'purchase')->update(['next_number' => $next + 1]);

    expect(failingIntegrity())->toBe(['voucher_numbers' => [sprintf('PUR-%06d missing', $next)]]);
});

it('T-20 a control-account line without a party fails control_account_parties and seals', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();
    // journal_lines refuse UPDATE and DELETE, so the fault is planted with INSERT instead: two
    // balanced lines on control account 2000, neither naming a party, added to an existing entry.
    // The entry stays balanced (one debit, one credit), so control_account_parties fails; the
    // added lines also leave the entry differing from its seal row (BR-LED-11), so seals does too.
    $line = DB::table('journal_lines')->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
        ->where('ledger_accounts.code', '2000')->orderBy('journal_lines.id')->first(['journal_lines.journal_entry_id', 'journal_lines.company_id', 'journal_lines.ledger_account_id']);
    DB::table('journal_lines')->insert([
        ['company_id' => $line->company_id, 'journal_entry_id' => $line->journal_entry_id, 'line_no' => 90, 'ledger_account_id' => $line->ledger_account_id, 'debit' => '1.00', 'credit' => '0.00'],
        ['company_id' => $line->company_id, 'journal_entry_id' => $line->journal_entry_id, 'line_no' => 91, 'ledger_account_id' => $line->ledger_account_id, 'debit' => '0.00', 'credit' => '1.00'],
    ]);

    expect(failingIntegrity())->toBe([
        'seals' => ["journal {$line->journal_entry_id}"],
        'control_account_parties' => ["journal {$line->journal_entry_id}"],
    ]);
});

it('T-20 an active entry pointing to another entry journal fails only entry_journals', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();
    [$a, $b] = DB::table('purchases')->whereNotNull('journal_entry_id')->orderBy('id')->limit(2)->get(['id', 'journal_entry_id'])->all();
    DB::table('purchases')->where('id', $a->id)->update(['journal_entry_id' => $b->journal_entry_id]);

    expect(failingIntegrity())->toBe(['entry_journals' => ["purchase:{$a->id} journal of another record"]]);
});

it('T-20 a cancelled entry without a reversal fails entry_journals', function () {
    AcceptanceRunner::load('at1')->setUp()->runAll();
    $purchase = DB::table('purchases')->whereNotNull('journal_entry_id')->where('status', 'active')->orderBy('id')->first(['id']);
    DB::table('purchases')->where('id', $purchase->id)->update(['status' => 'cancelled']);

    expect(failingIntegrity())->toBe(['entry_journals' => ["purchase:{$purchase->id} cancelled without reversal"]]);
});

it('T-20 --db-name checks the named database and writes no status file', function () {
    @unlink($this->verifyFile);

    // A separate process, so this test's own connection and transaction stay untouched.
    $named = Process::path(base_path())->run(['php', 'artisan', 'zb:verify', '--db-name='.config('database.connections.mysql.database')]);
    $missing = Process::path(base_path())->run(['php', 'artisan', 'zb:verify', '--db-name=zb_no_such_database']);
    $invalid = Process::path(base_path())->run(['php', 'artisan', 'zb:verify', '--db-name=bad;name']);

    expect($named->exitCode())->toBe(0)
        ->and($named->output())->toContain('OK journals_balance')
        ->and($missing->exitCode())->toBe(1)
        ->and($missing->output().$missing->errorOutput())->toContain('FAILED could not run the checks')
        ->and($invalid->exitCode())->toBe(1)
        ->and(is_file($this->verifyFile))->toBeFalse();
});

it('T-20 zb:verify is scheduled daily at 20:30 UTC without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'zb:verify'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 20 * * *')
        ->and($event->timezone)->toBe('UTC')
        ->and($event->withoutOverlapping)->toBeTrue();
});

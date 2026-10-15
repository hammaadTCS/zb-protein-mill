<?php

namespace App\Console\Commands;

use App\Domain\Ledger\IntegrityCheck;
use App\Support\Database\DatabaseGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * BR-OPS-08 (card T7.8): runs the integrity check and prints each check as OK or
 * FAILED with the IDs involved. With --db-name it checks that database instead (a
 * restored copy, `zb-restore --verify`). Read-only. Exit code 0 when all pass.
 *
 * Card T10.1: the database engine and the enforced session settings are checked first,
 * as the check named `database`, so a run on the wrong server fails before any select.
 */
class VerifyLedger extends Command
{
    protected $signature = 'zb:verify {--db-name= : check this database instead of the live one}';

    protected $description = 'Check that the books are whole (read-only)';

    public function handle(): int
    {
        $dbName = (string) $this->option('db-name');
        $live = $dbName === '';

        if (! $live) {
            if (preg_match('/^[A-Za-z0-9_]{1,63}$/', $dbName) !== 1) {
                $this->error('Give a database name of letters, digits and underscores.');

                return self::FAILURE;
            }

            // For this run only: the same server and role, another database.
            config(['database.connections.mysql.database' => $dbName]);
            DB::purge('mysql');
        }

        try {
            $results = IntegrityCheck::run();
        } catch (Throwable $e) {
            // Only the kind of error: its message could hold server details.
            $this->error('FAILED could not run the checks ('.class_basename($e).').');
            $results = [['check' => 'run', 'ok' => false, 'ids' => []]];
        }

        array_unshift($results, $this->databaseGuardResult());

        foreach ($results as $result) {
            $result['ok']
                ? $this->line("OK {$result['check']}")
                : $this->line("FAILED {$result['check']}".($result['ids'] === [] ? '' : ': '.implode(', ', $result['ids'])));
        }

        $failed = array_values(array_map(fn (array $r): string => $r['check'], array_filter($results, fn (array $r): bool => ! $r['ok'])));

        if ($live) {
            // zb-health-check looks for the exact text "result":"ok" (no pretty printing).
            file_put_contents(storage_path('app/zb-verify.json'), json_encode([
                'result' => $failed === [] ? 'ok' : 'failed',
                'finished_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'failed_checks' => $failed,
            ], JSON_THROW_ON_ERROR)."\n");
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Card T10.1: one result for the database guard, its ids being the failing items.
     *
     * @return array{check: string, ok: bool, ids: list<string>}
     */
    private function databaseGuardResult(): array
    {
        try {
            $failures = DatabaseGuard::failures();
        } catch (Throwable $e) {
            // Only the kind of error: its message could hold server details.
            $failures = ['could not reach the database ('.class_basename($e).')'];
        }

        return ['check' => 'database', 'ok' => $failures === [], 'ids' => $failures];
    }
}

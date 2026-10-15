<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\DatabaseGuard;
use Illuminate\Console\Command;
use Throwable;

/**
 * Card T10.1 / conventions.md §14a: refuse to run on a database that is not the MariaDB
 * of record with the connection settings the app enforces. Read-only. Exit code 0 only
 * when every item passes. `zb:verify` runs the same check.
 */
class CheckDatabase extends Command
{
    protected $signature = 'zb:db-check';

    protected $description = 'Check the database engine and the connection settings (read-only)';

    public function handle(): int
    {
        try {
            $failures = DatabaseGuard::failures();
        } catch (Throwable $e) {
            // Only the kind of error: its message could hold server details.
            $this->line('FAILED could not reach the database ('.class_basename($e).').');

            return self::FAILURE;
        }

        if ($failures === []) {
            $this->line('OK MariaDB with the enforced connection settings');

            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $this->line('FAILED '.$failure);
        }

        return self::FAILURE;
    }
}

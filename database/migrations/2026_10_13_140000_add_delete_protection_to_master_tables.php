<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Master tables built before this migration; later tables add their own trigger. */
    private const TABLES = ['companies', 'users', 'settings', 'ledger_accounts', 'voucher_sequences'];

    /**
     * Spec §9.7: block DELETE on every master and transaction table (BR-GEN-03).
     * Shared hosting gives one all-rights database user (BR-SEC-12, ADR-0014), so the trigger
     * is not a second lock behind a privilege: it is the only lock, and it is the one that
     * cannot be forgotten. MariaDB has one trigger per event, so each table names its own
     * (ADR-0016).
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::unprepared(
                "CREATE TRIGGER {$table}_prevent_delete BEFORE DELETE ON {$table}"
                ." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in {$table} cannot be deleted'"
            );
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_prevent_delete");
        }
    }
};

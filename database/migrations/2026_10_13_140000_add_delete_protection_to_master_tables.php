<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Master tables built before this migration; later tables add their own trigger. */
    private const TABLES = ['companies', 'users', 'settings', 'ledger_accounts', 'voucher_sequences'];

    /**
     * Spec §9.7: block DELETE on every master and transaction table (BR-GEN-03).
     * The web role also has no DELETE privilege on them (ADR-0007); this is the second lock.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_delete() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Rows in % cannot be deleted', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        foreach (self::TABLES as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_prevent_delete BEFORE DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION prevent_delete()");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_prevent_delete ON {$table}");
        }

        DB::unprepared('DROP FUNCTION IF EXISTS prevent_delete()');
    }
};

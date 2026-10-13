<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.4 journal tables and §9.7 protections (BR-LED-02, BR-LED-03, BR-LED-05).
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('entry_no', 20);
            $table->date('posting_date');
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->text('description');
            // BR-LED-05: a journal is reversed at most once.
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('journal_entries');
            $table->foreignId('created_by')->constrained('users');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['company_id', 'entry_no']);
            $table->index('posting_date');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('journal_entry_id')->constrained('journal_entries');
            $table->smallInteger('line_no');
            $table->foreignId('ledger_account_id')->index()->constrained('ledger_accounts');
            $table->unsignedBigInteger('party_id')->nullable()->index(); // foreign key added in T2.4
            $table->unsignedBigInteger('item_id')->nullable(); // foreign key added in T2.3
            $table->decimal('debit', 16, 2)->default(0);
            $table->decimal('credit', 16, 2)->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['journal_entry_id', 'line_no']);
        });

        DB::unprepared(<<<'SQL'
            -- BR-LED-02: exactly one side is greater than zero.
            ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_side
                CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0));

            -- BR-LED-05: journal rows are never updated or deleted. Reused by later tables.
            CREATE OR REPLACE FUNCTION prevent_change() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Rows in % cannot be updated or deleted', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER journal_entries_prevent_change BEFORE UPDATE OR DELETE ON journal_entries
                FOR EACH ROW EXECUTE FUNCTION prevent_change();
            CREATE TRIGGER journal_lines_prevent_change BEFORE UPDATE OR DELETE ON journal_lines
                FOR EACH ROW EXECUTE FUNCTION prevent_change();

            -- BR-LED-03: at commit (or SET CONSTRAINTS ... IMMEDIATE) every entry has at least
            -- two lines and its debit total equals its credit total.
            CREATE OR REPLACE FUNCTION check_journal_balance() RETURNS trigger AS $$
            DECLARE
                total_debit numeric;
                total_credit numeric;
                line_count integer;
            BEGIN
                SELECT COALESCE(SUM(debit), 0), COALESCE(SUM(credit), 0), COUNT(*)
                    INTO total_debit, total_credit, line_count
                    FROM journal_lines WHERE journal_entry_id = NEW.journal_entry_id;

                IF line_count < 2 THEN
                    RAISE EXCEPTION 'Journal entry % has fewer than 2 lines', NEW.journal_entry_id;
                END IF;

                IF total_debit <> total_credit THEN
                    RAISE EXCEPTION 'Journal entry % is not balanced: debit %, credit %',
                        NEW.journal_entry_id, total_debit, total_credit;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE CONSTRAINT TRIGGER journal_balance_check AFTER INSERT ON journal_lines
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION check_journal_balance();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        DB::unprepared('DROP FUNCTION IF EXISTS check_journal_balance()');
        // prevent_change() stays: later migrations' triggers use it and drop it themselves.
    }
};

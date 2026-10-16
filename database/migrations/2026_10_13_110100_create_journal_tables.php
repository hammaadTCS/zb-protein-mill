<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.4 journal tables, §9.5 journal_seals and §9.7 protections
     * (BR-LED-02, BR-LED-03, BR-LED-05, BR-LED-11, ADR-0016).
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('entry_no', 20)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->date('posting_date');
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->text('description');
            // BR-LED-05: a journal is reversed at most once.
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('journal_entries');
            $table->foreignId('created_by')->constrained('users');
            $table->dateTime('created_at', 3)->useCurrent();

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
            $table->dateTime('created_at', 3)->useCurrent();

            $table->unique(['journal_entry_id', 'line_no']);
        });

        Schema::create('journal_seals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            // One seal row per journal entry (BR-LED-11).
            $table->foreignId('journal_entry_id')->unique()->constrained('journal_entries');
            // What wrote the seal. PostingService is the only writer today (BR-LED-11).
            $table->string('source', 20)->default('posting');
            $table->unsignedInteger('line_count');
            $table->decimal('total_debit', 16, 2);
            $table->decimal('total_credit', 16, 2);
            $table->dateTime('sealed_at', 3)->useCurrent();
        });

        DB::unprepared(<<<'SQL'
            -- BR-LED-02: exactly one side is greater than zero.
            ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_side
                CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0));

            -- BR-LED-05: journal rows are never updated or deleted. MariaDB has one trigger
            -- per event, so each protected event gets its own trigger (ADR-0016).
            CREATE TRIGGER journal_entries_prevent_update BEFORE UPDATE ON journal_entries
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_entries cannot be updated or deleted';
            CREATE TRIGGER journal_entries_prevent_delete BEFORE DELETE ON journal_entries
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_entries cannot be updated or deleted';
            CREATE TRIGGER journal_lines_prevent_update BEFORE UPDATE ON journal_lines
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_lines cannot be updated or deleted';
            CREATE TRIGGER journal_lines_prevent_delete BEFORE DELETE ON journal_lines
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_lines cannot be updated or deleted';

            -- BR-LED-11: seal rows are never updated or deleted either.
            CREATE TRIGGER journal_seals_prevent_update BEFORE UPDATE ON journal_seals
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_seals cannot be updated or deleted';
            CREATE TRIGGER journal_seals_prevent_delete BEFORE DELETE ON journal_seals
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in journal_seals cannot be updated or deleted';

            -- BR-LED-03/BR-LED-11: PostingService writes the seal row at the end of the saving
            -- transaction. Summing the entry's lines again here and refusing the insert makes
            -- the whole save fail when an entry has fewer than two lines or its debit total
            -- does not equal its credit total — the MariaDB replacement for PostgreSQL's
            -- deferred constraint trigger, which MariaDB does not have (D-45, ADR-0016).
            CREATE TRIGGER journal_seals_before_insert BEFORE INSERT ON journal_seals
            FOR EACH ROW
            BEGIN
                DECLARE v_line_count INT DEFAULT 0;
                DECLARE v_total_debit DECIMAL(16, 2) DEFAULT 0.00;
                DECLARE v_total_credit DECIMAL(16, 2) DEFAULT 0.00;
                DECLARE v_message VARCHAR(128) DEFAULT '';

                SELECT COUNT(*), COALESCE(SUM(debit), 0), COALESCE(SUM(credit), 0)
                    INTO v_line_count, v_total_debit, v_total_credit
                    FROM journal_lines WHERE journal_entry_id = NEW.journal_entry_id;

                IF v_line_count < 2 THEN
                    SET v_message = CONCAT('Journal entry ', NEW.journal_entry_id, ' has fewer than 2 lines');
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
                END IF;

                IF v_total_debit <> v_total_credit THEN
                    SET v_message = CONCAT('Journal entry ', NEW.journal_entry_id, ' is not balanced: debit ', v_total_debit, ', credit ', v_total_credit);
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
                END IF;
            END;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS journal_seals_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS journal_seals_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS journal_seals_prevent_update');
        Schema::dropIfExists('journal_seals');

        DB::unprepared('DROP TRIGGER IF EXISTS journal_lines_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS journal_lines_prevent_update');
        Schema::dropIfExists('journal_lines');

        DB::unprepared('DROP TRIGGER IF EXISTS journal_entries_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS journal_entries_prevent_update');
        Schema::dropIfExists('journal_entries');
    }
};

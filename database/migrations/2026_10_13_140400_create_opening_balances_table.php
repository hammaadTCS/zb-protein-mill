<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.3 opening_balances plus voucher_no and the common columns (card T2.6).
     * One row per money account and per party at most (BR-OPB-02).
     */
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('voucher_no', 20)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->date('as_of_date');
            $table->foreignId('money_account_id')->nullable()->constrained('money_accounts');
            $table->foreignId('party_id')->nullable()->constrained('parties');
            $table->decimal('amount', 16, 2);
            $table->string('side', 10);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->unique(['company_id', 'voucher_no']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE opening_balances ADD CONSTRAINT opening_balances_target_check CHECK ((money_account_id IS NULL) <> (party_id IS NULL));
            ALTER TABLE opening_balances ADD CONSTRAINT opening_balances_amount_check CHECK (amount >= 0);
            ALTER TABLE opening_balances ADD CONSTRAINT opening_balances_side_check CHECK (
                (money_account_id IS NOT NULL AND side = 'balance')
                OR (party_id IS NOT NULL AND side IN ('we_owe', 'they_owe', 'capital'))
            );
            -- BR-OPB-02 (Q-8): MariaDB has no partial index, but a plain unique index lets a
            -- column hold many NULLs, so the row that does not use the column is not blocked.
            ALTER TABLE opening_balances ADD CONSTRAINT opening_balances_money_account_unique UNIQUE (company_id, money_account_id);
            ALTER TABLE opening_balances ADD CONSTRAINT opening_balances_party_unique UNIQUE (company_id, party_id);

            CREATE TRIGGER opening_balances_prevent_delete BEFORE DELETE ON opening_balances
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in opening_balances cannot be deleted';
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balances');
    }
};

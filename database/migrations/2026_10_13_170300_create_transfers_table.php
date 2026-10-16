<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Spec §9.3 transfers with the §9.1 transaction columns (D-22). */
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('voucher_no', 20)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->date('entry_date');
            $table->foreignId('from_account_id')->constrained('money_accounts');
            $table->foreignId('to_account_id')->constrained('money_accounts');
            $table->decimal('amount', 16, 2);
            $table->string('reference_no', 100)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 10)->default('active');
            $table->text('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->dateTime('cancelled_at', 3)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->uuid('idempotency_key')->nullable()->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->unique(['company_id', 'voucher_no']);
            $table->unique(['company_id', 'idempotency_key']); // BR-GEN-11
            $table->index(['company_id', 'entry_date']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE transfers ADD CONSTRAINT transfers_amount_check CHECK (amount > 0);
            ALTER TABLE transfers ADD CONSTRAINT transfers_status_check CHECK (status IN ('active', 'cancelled'));
            ALTER TABLE transfers ADD CONSTRAINT transfers_accounts_check CHECK (from_account_id <> to_account_id);

            CREATE TRIGGER transfers_prevent_delete BEFORE DELETE ON transfers
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in transfers cannot be deleted';
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};

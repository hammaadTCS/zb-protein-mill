<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Spec §9.3 partner_transactions with the §9.1 transaction columns (F-19, D-21). */
    public function up(): void
    {
        Schema::create('partner_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('voucher_no', 20);
            $table->date('entry_date');
            $table->foreignId('party_id')->constrained('parties');
            $table->string('type', 20);
            $table->decimal('amount', 16, 2);
            $table->foreignId('money_account_id')->constrained('money_accounts');
            $table->text('note')->nullable();
            $table->string('status', 10)->default('active');
            $table->text('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->uuid('idempotency_key')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestampsTz();

            $table->unique(['company_id', 'voucher_no']);
            $table->unique(['company_id', 'idempotency_key']); // BR-GEN-11
            $table->index(['company_id', 'entry_date']);
            $table->index(['party_id', 'entry_date']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE partner_transactions ADD CONSTRAINT partner_transactions_amount_check CHECK (amount > 0);
            ALTER TABLE partner_transactions ADD CONSTRAINT partner_transactions_status_check CHECK (status IN ('active', 'cancelled'));
            ALTER TABLE partner_transactions ADD CONSTRAINT partner_transactions_type_check CHECK (type IN ('investment', 'withdrawal'));

            CREATE TRIGGER partner_transactions_prevent_delete BEFORE DELETE ON partner_transactions
                FOR EACH ROW EXECUTE FUNCTION prevent_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_transactions');
    }
};

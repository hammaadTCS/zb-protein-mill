<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.3 payments with the §9.1 transaction columns. sale_id gets its foreign
     * key when the sales table exists (T3.4).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('voucher_no', 20);
            $table->date('entry_date');
            $table->string('direction', 10);
            $table->foreignId('party_id')->constrained('parties');
            $table->decimal('amount', 16, 2);
            $table->foreignId('money_account_id')->constrained('money_accounts');
            $table->string('reference_no', 100)->nullable();
            $table->foreignId('handled_by_party_id')->nullable()->constrained('parties');
            $table->foreignId('purchase_id')->nullable()->constrained('purchases');
            $table->unsignedBigInteger('sale_id')->nullable(); // foreign key added in T3.4
            $table->string('approval_status', 15);
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestampTz('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
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
            $table->index('approval_status');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT payments_direction_check CHECK (direction IN ('paid', 'received'));
            ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0);
            ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('active', 'cancelled'));
            ALTER TABLE payments ADD CONSTRAINT payments_approval_status_check CHECK (approval_status IN ('not_required', 'pending', 'approved', 'rejected'));
            ALTER TABLE payments ADD CONSTRAINT payments_link_check CHECK (purchase_id IS NULL OR sale_id IS NULL);

            CREATE TRIGGER payments_prevent_delete BEFORE DELETE ON payments
                FOR EACH ROW EXECUTE FUNCTION prevent_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

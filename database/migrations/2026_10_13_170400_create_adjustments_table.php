<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Spec §9.3 adjustments with the §9.1 transaction columns (F-11, F-12, D-13). */
    public function up(): void
    {
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('voucher_no', 20);
            $table->date('entry_date');
            $table->string('target_type', 10);
            $table->foreignId('purchase_id')->nullable()->constrained('purchases');
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->foreignId('party_id')->constrained('parties');
            $table->decimal('amount', 16, 2);
            $table->string('reason', 10);
            $table->text('note');
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
            $table->index('purchase_id');
            $table->index('sale_id');
            $table->index(['party_id', 'entry_date']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE adjustments ADD CONSTRAINT adjustments_amount_check CHECK (amount > 0);
            ALTER TABLE adjustments ADD CONSTRAINT adjustments_status_check CHECK (status IN ('active', 'cancelled'));
            ALTER TABLE adjustments ADD CONSTRAINT adjustments_reason_check CHECK (reason IN ('quality', 'weight', 'freight', 'other'));
            ALTER TABLE adjustments ADD CONSTRAINT adjustments_target_check CHECK (
                (target_type = 'purchase' AND purchase_id IS NOT NULL AND sale_id IS NULL)
                OR (target_type = 'sale' AND sale_id IS NOT NULL AND purchase_id IS NULL)
            );

            CREATE TRIGGER adjustments_prevent_delete BEFORE DELETE ON adjustments
                FOR EACH ROW EXECUTE FUNCTION prevent_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};

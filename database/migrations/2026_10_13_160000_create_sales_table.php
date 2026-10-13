<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.3 sales (same shape as purchases) with the §9.1 transaction columns.
     * kg is the buyer's weight; our_weight_kg the weight that left the plant (BR-SAL-05).
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('voucher_no', 20);
            $table->date('entry_date');
            $table->foreignId('party_id')->constrained('parties');
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('kg', 14, 3);
            $table->decimal('rate', 14, 4)->nullable();
            $table->decimal('amount', 16, 2)->nullable();
            $table->string('rate_status', 10);
            $table->timestampTz('rate_fixed_at')->nullable();
            $table->string('slip_no', 50)->nullable();
            $table->decimal('our_weight_kg', 14, 3)->nullable();
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
            $table->index('rate_status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE sales ADD CONSTRAINT sales_kg_check CHECK (kg > 0);
            ALTER TABLE sales ADD CONSTRAINT sales_our_weight_check CHECK (our_weight_kg IS NULL OR our_weight_kg > 0);
            ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('active', 'cancelled'));
            ALTER TABLE sales ADD CONSTRAINT sales_rate_status_check CHECK (
                (rate_status = 'pending' AND rate IS NULL AND amount IS NULL)
                OR (rate_status = 'fixed' AND rate > 0 AND amount IS NOT NULL)
            );

            CREATE TRIGGER sales_prevent_delete BEFORE DELETE ON sales
                FOR EACH ROW EXECUTE FUNCTION prevent_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });

        Schema::dropIfExists('sales');
    }
};

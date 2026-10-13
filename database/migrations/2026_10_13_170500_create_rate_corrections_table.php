<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Spec §9.3 rate_corrections (D-27, BR-LED-09): a rate change for an entry in a closed month. */
    public function up(): void
    {
        Schema::create('rate_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('voucher_no', 20);
            $table->date('correction_date');
            $table->string('target_type', 10);
            $table->foreignId('purchase_id')->nullable()->constrained('purchases');
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->decimal('old_rate', 14, 4);
            $table->decimal('new_rate', 14, 4);
            $table->decimal('old_amount', 16, 2);
            $table->decimal('new_amount', 16, 2);
            $table->decimal('difference', 16, 2);
            $table->text('reason');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestampsTz();

            $table->unique(['company_id', 'voucher_no']);
            $table->index(['company_id', 'correction_date']);
            $table->index('purchase_id');
            $table->index('sale_id');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE rate_corrections ADD CONSTRAINT rate_corrections_difference_check CHECK (difference <> 0);
            ALTER TABLE rate_corrections ADD CONSTRAINT rate_corrections_target_check CHECK (
                (target_type = 'purchase' AND purchase_id IS NOT NULL AND sale_id IS NULL)
                OR (target_type = 'sale' AND sale_id IS NOT NULL AND purchase_id IS NULL)
            );

            CREATE TRIGGER rate_corrections_prevent_delete BEFORE DELETE ON rate_corrections
                FOR EACH ROW EXECUTE FUNCTION prevent_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_corrections');
    }
};

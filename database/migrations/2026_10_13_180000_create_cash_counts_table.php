<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Spec §9.5 cash_counts (D-25, BR-CHK-01 to BR-CHK-04): stored, never posted. */
    public function up(): void
    {
        Schema::create('cash_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->date('count_date');
            $table->foreignId('money_account_id')->constrained('money_accounts');
            $table->decimal('counted_amount', 16, 2)->nullable();
            $table->decimal('system_amount', 16, 2);
            $table->decimal('difference', 16, 2)->nullable();
            $table->text('note')->nullable();
            $table->boolean('business_closed')->default(false);
            $table->foreignId('counted_by')->constrained('users');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'count_date']);
            $table->index(['money_account_id', 'count_date']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE cash_counts ADD CONSTRAINT cash_counts_counted_check CHECK (
                (business_closed AND counted_amount IS NULL AND difference IS NULL)
                OR (NOT business_closed AND counted_amount IS NOT NULL AND counted_amount >= 0 AND difference IS NOT NULL)
            );

            CREATE TRIGGER cash_counts_prevent_delete BEFORE DELETE ON cash_counts
                FOR EACH ROW EXECUTE FUNCTION prevent_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.5 bank_reconciliations (D-26, BR-MEC-05): the bank's closing balance for a
     * month against the ledger. Never changed; the latest row per account and month is used.
     */
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('period_id')->constrained('periods');
            $table->foreignId('money_account_id')->constrained('money_accounts');
            $table->decimal('statement_balance', 16, 2);
            $table->decimal('system_balance', 16, 2);
            $table->decimal('difference', 16, 2);
            $table->text('note')->nullable();
            $table->foreignId('entered_by')->constrained('users');
            $table->timestampTz('entered_at')->useCurrent();

            $table->index(['period_id', 'money_account_id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER bank_reconciliations_prevent_delete BEFORE DELETE ON bank_reconciliations
                FOR EACH ROW EXECUTE FUNCTION prevent_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};

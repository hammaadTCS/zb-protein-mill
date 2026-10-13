<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.2 money_accounts (F-14, D-22): each cash or bank account has its own
     * ledger account (BR-ACC-01).
     */
    public function up(): void
    {
        Schema::create('money_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name', 100);
            $table->string('type', 10);
            $table->string('bank_name', 150)->nullable();
            $table->string('account_title', 150)->nullable();
            $table->string('account_number', 150)->nullable();
            $table->foreignId('ledger_account_id')->unique()->constrained('ledger_accounts');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestampsTz();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE money_accounts ADD CONSTRAINT money_accounts_type_check CHECK (type IN ('cash', 'bank'));

            CREATE TRIGGER money_accounts_prevent_delete BEFORE DELETE ON money_accounts
                FOR EACH ROW EXECUTE FUNCTION prevent_delete();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('money_accounts');
    }
};

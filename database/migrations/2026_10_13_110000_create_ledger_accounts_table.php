<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §8.1 chart of accounts, §9.2 ledger_accounts.
     */
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('code', 10)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->string('name', 150)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->string('type', 20);
            $table->string('normal_side', 10);
            $table->string('party_type', 20)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->unique(['company_id', 'code']);
        });

        DB::statement("ALTER TABLE ledger_accounts ADD CONSTRAINT ledger_accounts_type_check CHECK (type IN ('asset', 'liability', 'equity', 'income', 'expense'))");
        DB::statement("ALTER TABLE ledger_accounts ADD CONSTRAINT ledger_accounts_normal_side_check CHECK (normal_side IN ('debit', 'credit'))");
        DB::statement("ALTER TABLE ledger_accounts ADD CONSTRAINT ledger_accounts_party_type_check CHECK (party_type IS NULL OR party_type IN ('supplier', 'buyer', 'partner', 'employee', 'other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.2 expense_categories (F-20, D-18): one flat list; each category has its
     * own ledger account (spec §8.1, 6000+).
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name', 100)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->foreignId('ledger_account_id')->unique()->constrained('ledger_accounts');
            $table->boolean('is_quick')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->unique(['company_id', 'name']);
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER expense_categories_prevent_delete BEFORE DELETE ON expense_categories
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in expense_categories cannot be deleted';
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};

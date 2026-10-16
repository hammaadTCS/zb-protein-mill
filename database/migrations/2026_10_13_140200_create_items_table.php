<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.2 items (D-07, BR-ITM-01) plus `code` (card T2.3). Raw material and fuel
     * link to a purchase account, products to a sales account.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('code', 10)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->string('name', 100)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->string('type', 20);
            $table->string('unit', 10)->default('kg');
            $table->foreignId('purchase_ledger_account_id')->nullable()->constrained('ledger_accounts');
            $table->foreignId('sales_ledger_account_id')->nullable()->constrained('ledger_accounts');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->unique(['company_id', 'code']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE items ADD CONSTRAINT items_type_check CHECK (type IN ('raw_material', 'fuel', 'product'));
            ALTER TABLE items ADD CONSTRAINT items_ledger_account_check CHECK (
                (type IN ('raw_material', 'fuel') AND purchase_ledger_account_id IS NOT NULL AND sales_ledger_account_id IS NULL)
                OR (type = 'product' AND sales_ledger_account_id IS NOT NULL AND purchase_ledger_account_id IS NULL)
            );

            CREATE TRIGGER items_prevent_delete BEFORE DELETE ON items
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in items cannot be deleted';
            SQL);

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });

        Schema::dropIfExists('items');
    }
};

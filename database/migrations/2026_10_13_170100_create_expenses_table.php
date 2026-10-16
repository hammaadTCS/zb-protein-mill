<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Spec §9.3 expenses with the §9.1 transaction columns (F-17, F-20, F-21). */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('voucher_no', 20)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->date('entry_date');
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->decimal('amount', 16, 2);
            $table->string('payment_mode', 20);
            $table->foreignId('money_account_id')->nullable()->constrained('money_accounts');
            $table->foreignId('party_id')->nullable()->constrained('parties');
            $table->foreignId('handled_by_party_id')->nullable()->constrained('parties');
            $table->foreignId('linked_purchase_id')->nullable()->constrained('purchases');
            $table->foreignId('linked_sale_id')->nullable()->constrained('sales');
            $table->text('note');
            $table->string('status', 10)->default('active');
            $table->text('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->dateTime('cancelled_at', 3)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->uuid('idempotency_key')->nullable()->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->unique(['company_id', 'voucher_no']);
            $table->unique(['company_id', 'idempotency_key']); // BR-GEN-11
            $table->index(['company_id', 'entry_date']);
            $table->index(['party_id', 'entry_date']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE expenses ADD CONSTRAINT expenses_amount_check CHECK (amount > 0);
            ALTER TABLE expenses ADD CONSTRAINT expenses_status_check CHECK (status IN ('active', 'cancelled'));
            ALTER TABLE expenses ADD CONSTRAINT expenses_payment_mode_check CHECK (
                (payment_mode = 'paid' AND money_account_id IS NOT NULL)
                OR (payment_mode IN ('unpaid', 'paid_by_employee') AND money_account_id IS NULL AND party_id IS NOT NULL)
            );
            ALTER TABLE expenses ADD CONSTRAINT expenses_link_check CHECK (linked_purchase_id IS NULL OR linked_sale_id IS NULL);

            CREATE TRIGGER expenses_prevent_delete BEFORE DELETE ON expenses
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in expenses cannot be deleted';
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

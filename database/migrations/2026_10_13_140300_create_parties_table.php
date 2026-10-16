<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.2 parties (D-01 to D-06): everyone the mill deals with. No CNIC (F-22).
     */
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('code', 10)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->string('type', 10);
            $table->string('name', 150)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->string('name_ur', 150)->nullable();
            $table->string('business_name', 150)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('mobile', 20);
            $table->string('area', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('ntn', 30)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_account_title', 150)->nullable();
            $table->string('bank_account_number', 150)->nullable();
            $table->decimal('credit_limit', 16, 2)->nullable();
            $table->integer('payment_terms_days')->nullable();
            $table->decimal('profit_share_percent', 5, 2)->nullable();
            $table->date('joining_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 10)->default('active');
            $table->foreignId('merged_into_id')->nullable()->constrained('parties');
            $table->boolean('needs_review')->default(false);
            $table->boolean('bank_change_needs_review')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at', 3)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type', 'status']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE parties ADD CONSTRAINT parties_type_check CHECK (type IN ('supplier', 'buyer', 'partner', 'employee', 'other'));
            ALTER TABLE parties ADD CONSTRAINT parties_status_check CHECK (status IN ('active', 'inactive'));
            -- BR-PTY-10: buyer-only and partner-only fields.
            ALTER TABLE parties ADD CONSTRAINT parties_buyer_fields_check CHECK (type = 'buyer' OR (credit_limit IS NULL AND payment_terms_days IS NULL));
            ALTER TABLE parties ADD CONSTRAINT parties_partner_fields_check CHECK (type = 'partner' OR (profit_share_percent IS NULL AND joining_date IS NULL));

            CREATE TRIGGER parties_prevent_delete BEFORE DELETE ON parties
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in parties cannot be deleted';
            SQL);

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreign('party_id')->references('id')->on('parties');
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropForeign(['party_id']);
        });

        Schema::dropIfExists('parties');
    }
};

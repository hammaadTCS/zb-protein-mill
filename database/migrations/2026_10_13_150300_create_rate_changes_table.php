<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.5 rate_changes (BR-PUR-09): every rate added or changed, never updated
     * or deleted (spec §9.7).
     */
    public function up(): void
    {
        Schema::create('rate_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('target_type', 10);
            $table->unsignedBigInteger('target_id');
            $table->decimal('old_rate', 14, 4)->nullable();
            $table->decimal('new_rate', 14, 4);
            $table->decimal('old_amount', 16, 2)->nullable();
            $table->decimal('new_amount', 16, 2);
            $table->foreignId('changed_by')->constrained('users');
            $table->dateTime('changed_at', 3);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->index(['target_type', 'target_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE rate_changes ADD CONSTRAINT rate_changes_target_type_check CHECK (target_type IN ('purchase', 'sale'));

            -- MariaDB has one trigger per event, so each protected event has its own trigger.
            CREATE TRIGGER rate_changes_prevent_update BEFORE UPDATE ON rate_changes
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in rate_changes cannot be updated or deleted';
            CREATE TRIGGER rate_changes_prevent_delete BEFORE DELETE ON rate_changes
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in rate_changes cannot be updated or deleted';
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_changes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.5 audit_logs (BR-AUD-01). BR-AUD-02: rows are never changed or deleted;
     * the table's own triggers refuse both (§9.7).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('user_id')->nullable()->constrained('users'); // null: failed login of an unknown e-mail, server commands
            $table->string('action', 60);
            $table->string('auditable_type', 40)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('created_at', 3)->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['company_id', 'created_at']);
            $table->index('user_id');
        });

        DB::unprepared(<<<'SQL'
            -- MariaDB has one trigger per event, so each protected event has its own trigger.
            CREATE TRIGGER audit_logs_prevent_update BEFORE UPDATE ON audit_logs
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in audit_logs cannot be updated or deleted';
            CREATE TRIGGER audit_logs_prevent_delete BEFORE DELETE ON audit_logs
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in audit_logs cannot be updated or deleted';
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

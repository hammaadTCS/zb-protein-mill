<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.5 audit_logs (BR-AUD-01). BR-AUD-02: rows are never changed or deleted;
     * the prevent_change() trigger from the journal migration refuses it.
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
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['company_id', 'created_at']);
            $table->index('user_id');
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_prevent_change BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_change();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

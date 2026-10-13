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
            $table->timestampTz('changed_at');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestampsTz();

            $table->index(['target_type', 'target_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE rate_changes ADD CONSTRAINT rate_changes_target_type_check CHECK (target_type IN ('purchase', 'sale'));

            CREATE TRIGGER rate_changes_prevent_change BEFORE UPDATE OR DELETE ON rate_changes
                FOR EACH ROW EXECUTE FUNCTION prevent_change();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_changes');
    }
};

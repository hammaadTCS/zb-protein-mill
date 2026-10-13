<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.5 periods and period_events (D-26, BR-MEC-07). A month without a row is open.
     */
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->smallInteger('year');
            $table->smallInteger('month');
            $table->string('status', 10)->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestampsTz();

            $table->unique(['company_id', 'year', 'month']);
        });

        Schema::create('period_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('period_id')->constrained('periods');
            $table->string('event', 10);
            $table->foreignId('user_id')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE periods ADD CONSTRAINT periods_status_check CHECK (status IN ('open', 'closed'));
            ALTER TABLE periods ADD CONSTRAINT periods_month_check CHECK (month BETWEEN 1 AND 12);
            ALTER TABLE period_events ADD CONSTRAINT period_events_event_check CHECK (event IN ('closed', 'reopened'));

            -- Spec §9.7: period events are never changed or deleted.
            CREATE TRIGGER period_events_prevent_change BEFORE UPDATE OR DELETE ON period_events
                FOR EACH ROW EXECUTE FUNCTION prevent_change();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('period_events');
        Schema::dropIfExists('periods');
    }
};

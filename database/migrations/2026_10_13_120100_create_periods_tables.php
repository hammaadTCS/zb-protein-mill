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
            $table->dateTime('closed_at', 3)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->unique(['company_id', 'year', 'month']);
        });

        Schema::create('period_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('period_id')->constrained('periods');
            $table->string('event', 10);
            $table->foreignId('user_id')->constrained('users');
            $table->text('reason')->nullable();
            $table->dateTime('created_at', 3)->useCurrent();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE periods ADD CONSTRAINT periods_status_check CHECK (status IN ('open', 'closed'));
            ALTER TABLE periods ADD CONSTRAINT periods_month_check CHECK (month BETWEEN 1 AND 12);
            ALTER TABLE period_events ADD CONSTRAINT period_events_event_check CHECK (event IN ('closed', 'reopened'));

            -- Spec §9.7: period events are never changed or deleted.
            -- MariaDB has one trigger per event, so each protected event has its own trigger.
            CREATE TRIGGER period_events_prevent_update BEFORE UPDATE ON period_events
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in period_events cannot be updated or deleted';
            CREATE TRIGGER period_events_prevent_delete BEFORE DELETE ON period_events
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in period_events cannot be updated or deleted';
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('period_events');
        Schema::dropIfExists('periods');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.2 settings and §9.6 keys. OPEN values are stored as NULL (no default).
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('key', 80)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->text('value')->nullable();
            $table->string('value_type', 20);
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->unique(['company_id', 'key']);
        });

        DB::statement("ALTER TABLE settings ADD CONSTRAINT settings_value_type_check CHECK (value_type IN ('string', 'integer', 'decimal', 'boolean', 'date', 'choice'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

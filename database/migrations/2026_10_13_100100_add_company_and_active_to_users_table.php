<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BR-GEN-01: users belong to a company. BR-USR-03: users are disabled, never deleted.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->index()->constrained('companies');
            $table->boolean('is_active')->default(true);
        });

        // Users that already exist (for example the local test user) join the company.
        if (DB::table('users')->exists()) {
            $companyId = DB::table('companies')->orderBy('id')->value('id')
                ?? DB::table('companies')->insertGetId([
                    'name' => 'ZB Poultry Protein Mill',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('users')->whereNull('company_id')->update(['company_id' => $companyId]);
        }

        DB::statement('ALTER TABLE users MODIFY company_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('is_active');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Q-4: MariaDB has no advisory locks, so month-end close and every date-checked save
 * take one row per company instead: SELECT … FOR SHARE (shared) or FOR UPDATE
 * (exclusive) inside the same transaction, released at commit or rollback exactly
 * like pg_advisory_xact_lock[_shared] was. The row is created on first use
 * (Periods::lockRow), so an upgraded database and the test suite need nothing here;
 * the INSERT … SELECT copies a row for the companies that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_locks', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->primary();
            $table->foreign('company_id')->references('id')->on('companies');
        });

        DB::table('company_locks')->insertUsing(
            ['company_id'],
            DB::table('companies')->select('id'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('company_locks');
    }
};

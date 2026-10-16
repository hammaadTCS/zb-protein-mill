<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Every table whose rows open from a link (spec §11.1 routes with a record). */
    private const TABLES = [
        'parties', 'purchases', 'sales', 'payments', 'expenses', 'transfers', 'partner_transactions',
        'adjustments', 'attachments', 'money_accounts', 'items', 'expense_categories', 'users',
    ];

    /**
     * BR-SEC-19: a random public id for links and address-bar filters, so URLs never show
     * the internal number. The database fills it, for existing rows now and for rows saved
     * by the previous release after a rollback (BR-OPS-10), so the column is never empty.
     */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->uuid('public_id')->default(new Expression('(UUID())'))->unique()->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('public_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BR-GEN-05: one running number per company and document type, never reset.
     */
    public function up(): void
    {
        Schema::create('voucher_sequences', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('companies');
            $table->string('doc_type', 40)->collation('utf8mb4_nopad_bin'); // §14c unique-rule column
            $table->string('prefix', 10);
            $table->smallInteger('width');
            $table->bigInteger('next_number')->default(1);

            $table->primary(['company_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_sequences');
    }
};

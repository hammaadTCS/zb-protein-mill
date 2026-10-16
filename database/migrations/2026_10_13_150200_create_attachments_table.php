<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9.5 attachments. Never deleted; a replaced photo stays linked
     * (replaced_by_id points to the new one).
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('attachable_type', 40);
            $table->unsignedBigInteger('attachable_id');
            $table->string('kind', 20);
            $table->string('disk', 30);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('replaced_by_id')->nullable()->constrained('attachments');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->index(['attachable_type', 'attachable_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attachments ADD CONSTRAINT attachments_kind_check CHECK (kind IN ('slip_photo', 'receipt_photo', 'other'));

            CREATE TRIGGER attachments_prevent_delete BEFORE DELETE ON attachments
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rows in attachments cannot be deleted';
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};

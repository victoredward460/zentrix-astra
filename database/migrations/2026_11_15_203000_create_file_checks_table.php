<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('storage_key', 512)->unique();
            $table->string('original_name', 255);
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type', 127)->default('application/octet-stream');
            $table->char('sha256', 64)->nullable();
            $table->string('status', 20)->default('processing')->index();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_checks');
    }
};

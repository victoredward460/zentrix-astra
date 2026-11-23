<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_learning_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('title', 160);
            $table->string('topic', 160);
            $table->string('level', 20);
            $table->json('content');
            $table->string('html_path', 255)->unique();
            $table->string('pdf_path', 255)->unique();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_learning_materials');
    }
};

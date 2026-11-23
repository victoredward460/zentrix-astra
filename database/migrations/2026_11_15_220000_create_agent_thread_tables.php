<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamp('last_activity_at')->index();
            $table->timestamps();

            $table->unique(['id', 'user_id']);
            $table->index(['user_id', 'last_activity_at']);
        });

        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('status', 20);
            $table->longText('content')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('model', 160)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();

            $table->foreign(['thread_id', 'user_id'], 'agent_messages_thread_owner_foreign')
                ->references(['id', 'user_id'])
                ->on('agent_threads')
                ->cascadeOnDelete();
            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
        Schema::dropIfExists('agent_threads');
    }
};

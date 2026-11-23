<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_run_id')->constrained()->cascadeOnDelete();
            $table->string('check_key', 64);
            $table->string('outcome', 20);
            $table->string('summary', 255);
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->unique(['scan_run_id', 'check_key']);
        });

        Schema::create('security_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('scan_run_id');
            $table->string('rule_key', 64);
            $table->string('title', 180);
            $table->text('description');
            $table->string('severity', 20);
            $table->string('verification_state', 20)->default('unverified');
            $table->string('state', 20)->default('open');
            $table->json('evidence')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->foreign(['scan_run_id', 'user_id'], 'security_findings_run_owner_foreign')
                ->references(['id', 'user_id'])
                ->on('scan_runs')
                ->cascadeOnDelete();
            $table->unique(['scan_run_id', 'rule_key']);
            $table->index(['user_id', 'state', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_findings');
        Schema::dropIfExists('scan_check_results');
    }
};

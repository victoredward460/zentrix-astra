<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('security_asset_id');
            $table->string('status', 20)->default('requested')->index();
            $table->timestamp('requested_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamps();

            $table->foreign(['security_asset_id', 'user_id'], 'scan_runs_asset_owner_foreign')
                ->references(['id', 'user_id'])
                ->on('security_assets')
                ->cascadeOnDelete();
            $table->unique(['id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_runs');
    }
};

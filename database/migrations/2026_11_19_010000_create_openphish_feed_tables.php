<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openphish_indicators', function (Blueprint $table): void {
            $table->id();
            $table->text('url');
            $table->char('url_hash', 64)->unique();
            $table->string('host', 253)->index();
            $table->timestamps();
        });

        Schema::create('openphish_feed_status', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('last_successful_at')->nullable();
            $table->string('last_update_result', 20)->default('never_updated');
            $table->unsignedInteger('indicator_count')->default(0);
            $table->string('last_error', 80)->nullable();
            $table->timestamps();
        });

        DB::table('openphish_feed_status')->insert([
            'id' => 1,
            'last_update_result' => 'never_updated',
            'indicator_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('openphish_feed_status');
        Schema::dropIfExists('openphish_indicators');
    }
};

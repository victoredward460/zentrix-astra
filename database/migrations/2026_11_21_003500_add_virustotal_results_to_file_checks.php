<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_checks', function (Blueprint $table) {
            $table->string('vt_status', 32)->nullable()->after('sha256');
            $table->unsignedSmallInteger('vt_malicious')->nullable()->after('vt_status');
            $table->unsignedSmallInteger('vt_suspicious')->nullable()->after('vt_malicious');
            $table->unsignedSmallInteger('vt_harmless')->nullable()->after('vt_suspicious');
            $table->unsignedSmallInteger('vt_undetected')->nullable()->after('vt_harmless');
            $table->string('vt_error_code', 64)->nullable()->after('vt_undetected');
            $table->unsignedInteger('vt_retry_after_seconds')->nullable()->after('vt_error_code');
            $table->timestamp('vt_last_checked_at')->nullable()->after('vt_retry_after_seconds');
            $table->index('vt_status');
        });
    }

    public function down(): void
    {
        Schema::table('file_checks', function (Blueprint $table) {
            $table->dropIndex(['vt_status']);
            $table->dropColumn([
                'vt_status',
                'vt_malicious',
                'vt_suspicious',
                'vt_harmless',
                'vt_undetected',
                'vt_error_code',
                'vt_retry_after_seconds',
                'vt_last_checked_at',
            ]);
        });
    }
};

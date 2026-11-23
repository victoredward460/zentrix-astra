<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_runs', function (Blueprint $table) {
            $table->string('scan_type', 32)->default('security_scanner');
            $table->index(['user_id', 'scan_type', 'created_at'], 'scan_runs_owner_type_created_index');
        });

        Schema::table('security_findings', function (Blueprint $table) {
            $table->text('recommended_action')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('security_findings', function (Blueprint $table) {
            $table->dropColumn('recommended_action');
        });

        Schema::table('scan_runs', function (Blueprint $table) {
            $table->dropIndex('scan_runs_owner_type_created_index');
            $table->dropColumn('scan_type');
        });
    }
};

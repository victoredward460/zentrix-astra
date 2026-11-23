<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_feature_settings', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $features = [
            'public-registration',
            'scanner',
            'website-auditor',
            'file-check',
            'ai-agent',
            'academy',
        ];

        DB::table('platform_feature_settings')->insert(array_map(
            static fn (string $feature): array => [
                'feature_key' => $feature,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $features,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_feature_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->json('gbp_snapshot')->nullable();
            $table->timestamp('gbp_snapshot_fetched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['gbp_snapshot', 'gbp_snapshot_fetched_at']);
        });
    }
};

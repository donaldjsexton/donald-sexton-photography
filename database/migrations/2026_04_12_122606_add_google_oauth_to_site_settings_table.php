<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('google_connected_email')->nullable()->after('google_analytics_measurement_id');
            $table->text('google_access_token')->nullable()->after('google_connected_email');
            $table->text('google_refresh_token')->nullable()->after('google_access_token');
            $table->unsignedBigInteger('google_token_expires_at')->nullable()->after('google_refresh_token');
            $table->json('google_granted_scopes')->nullable()->after('google_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_connected_email',
                'google_access_token',
                'google_refresh_token',
                'google_token_expires_at',
                'google_granted_scopes',
            ]);
        });
    }
};

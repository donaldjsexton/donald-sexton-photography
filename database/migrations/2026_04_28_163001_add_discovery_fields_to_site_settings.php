<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('instagram_url')->nullable();
            $table->string('pinterest_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('x_url')->nullable();
            $table->string('google_site_verification')->nullable();
            $table->string('bing_site_verification')->nullable();
            $table->string('pinterest_site_verification')->nullable();
            $table->string('indexnow_key', 128)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'instagram_url',
                'pinterest_url',
                'facebook_url',
                'youtube_url',
                'tiktok_url',
                'x_url',
                'google_site_verification',
                'bing_site_verification',
                'pinterest_site_verification',
                'indexnow_key',
            ]);
        });
    }
};

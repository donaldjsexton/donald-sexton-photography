<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wedding_stories', function (Blueprint $table) {
            $table->unsignedBigInteger('original_wp_post_id')->nullable()->after('canonical_url');
            $table->string('original_wp_url')->nullable()->after('original_wp_post_id');

            $table->index('original_wp_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('wedding_stories', function (Blueprint $table) {
            $table->dropIndex(['original_wp_post_id']);
            $table->dropColumn(['original_wp_post_id', 'original_wp_url']);
        });
    }
};

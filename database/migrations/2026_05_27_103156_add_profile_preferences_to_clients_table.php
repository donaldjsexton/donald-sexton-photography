<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('communication_preferences')->nullable()->after('notes');
            $table->boolean('social_media_consent')->default(false)->after('communication_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['communication_preferences', 'social_media_consent']);
        });
    }
};

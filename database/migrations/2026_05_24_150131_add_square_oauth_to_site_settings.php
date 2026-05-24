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
            $table->string('square_merchant_id')->nullable()->after('google_granted_scopes');
            $table->text('square_access_token')->nullable()->after('square_merchant_id');
            $table->text('square_refresh_token')->nullable()->after('square_access_token');
            $table->timestamp('square_token_expires_at')->nullable()->after('square_refresh_token');
            $table->string('square_location_id')->nullable()->after('square_token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'square_merchant_id',
                'square_access_token',
                'square_refresh_token',
                'square_token_expires_at',
                'square_location_id',
            ]);
        });
    }
};

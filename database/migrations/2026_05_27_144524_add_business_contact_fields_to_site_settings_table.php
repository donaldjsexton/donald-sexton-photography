<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('business_phone', 32)->nullable();
            $table->string('business_email')->nullable();
            $table->string('business_street_address')->nullable();
            $table->string('business_locality', 128)->nullable();
            $table->string('business_region', 64)->nullable();
            $table->string('business_postal_code', 32)->nullable();
            $table->string('business_country', 2)->nullable();
            $table->decimal('business_latitude', 10, 7)->nullable();
            $table->decimal('business_longitude', 10, 7)->nullable();
            $table->string('business_hours_note')->nullable();
            $table->string('business_price_range', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'business_phone',
                'business_email',
                'business_street_address',
                'business_locality',
                'business_region',
                'business_postal_code',
                'business_country',
                'business_latitude',
                'business_longitude',
                'business_hours_note',
                'business_price_range',
            ]);
        });
    }
};

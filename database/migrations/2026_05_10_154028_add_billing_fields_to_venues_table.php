<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('business_name')->nullable()->after('name');
            $table->string('billing_email')->nullable();
            $table->string('billing_contact_name')->nullable();
            $table->string('billing_address_line_1')->nullable();
            $table->string('billing_address_line_2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_postal_code')->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->string('net_payment_terms')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->index('billing_email');
        });

        Schema::create('venue_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_password_reset_tokens');

        Schema::table('venues', function (Blueprint $table) {
            $table->dropIndex(['billing_email']);
            $table->dropColumn([
                'business_name',
                'billing_email',
                'billing_contact_name',
                'billing_address_line_1',
                'billing_address_line_2',
                'billing_city',
                'billing_state',
                'billing_postal_code',
                'billing_country',
                'net_payment_terms',
                'password',
                'remember_token',
                'email_verified_at',
                'last_login_at',
            ]);
        });
    }
};

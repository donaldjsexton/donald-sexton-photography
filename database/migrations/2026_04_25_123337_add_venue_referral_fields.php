<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('email_secondary')->nullable()->after('email');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->json('referral_emails')->nullable()->after('website_url');
            $table->string('referral_contact_name')->nullable()->after('referral_emails');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn('email_secondary');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['referral_emails', 'referral_contact_name']);
        });
    }
};

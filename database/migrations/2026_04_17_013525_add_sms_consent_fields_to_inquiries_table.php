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
        Schema::table('inquiries', function (Blueprint $table) {
            $table->boolean('sms_opt_in_transactional')->default(false)->after('phone');
            $table->boolean('sms_opt_in_marketing')->default(false)->after('sms_opt_in_transactional');
            $table->timestamp('sms_consent_at')->nullable()->after('sms_opt_in_marketing');
            $table->string('sms_consent_ip', 45)->nullable()->after('sms_consent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn([
                'sms_opt_in_transactional',
                'sms_opt_in_marketing',
                'sms_consent_at',
                'sms_consent_ip',
            ]);
        });
    }
};

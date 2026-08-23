<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booked_jobs', function (Blueprint $table) {
            $table->date('event_date')->nullable()->change();
            $table->date('previous_event_date')->nullable()->after('event_date');
            $table->timestamp('rescheduled_at')->nullable()->after('previous_event_date');
            $table->text('reschedule_reason')->nullable()->after('rescheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('booked_jobs', function (Blueprint $table) {
            $table->dropColumn(['previous_event_date', 'rescheduled_at', 'reschedule_reason']);
        });

        Schema::table('booked_jobs', function (Blueprint $table) {
            $table->date('event_date')->nullable(false)->change();
        });
    }
};

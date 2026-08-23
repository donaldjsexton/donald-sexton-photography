<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            // No default and nullable: an unstated type is unknown, not a wedding.
            $table->string('event_type')->nullable()->default(null)->change();
        });

        Schema::table('booked_jobs', function (Blueprint $table) {
            $table->string('event_type')->nullable()->after('couple_names');
        });
    }

    public function down(): void
    {
        Schema::table('booked_jobs', function (Blueprint $table) {
            $table->dropColumn('event_type');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('event_type')->default('wedding')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link client galleries to the CRM: an optional owning client and booked
     * job, plus an opt-in payment gate (default off) that withholds full-
     * resolution downloads until the client's balance is settled.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('site_id')->constrained()->nullOnDelete();
            $table->foreignId('booked_job_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->boolean('requires_payment')->default(false)->after('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('booked_job_id');
            $table->dropColumn('requires_payment');
        });
    }
};

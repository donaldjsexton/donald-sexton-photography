<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booked_jobs', function (Blueprint $table): void {
            $table->foreignId('inquiry_id')
                ->nullable()
                ->after('id')
                ->constrained('inquiries')
                ->nullOnDelete();

            $table->string('google_event_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('booked_jobs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inquiry_id');
            $table->string('google_event_id')->nullable(false)->change();
        });
    }
};

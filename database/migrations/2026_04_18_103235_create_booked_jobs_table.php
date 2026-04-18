<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booked_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('google_event_id')->unique();
            $table->string('summary');
            $table->string('couple_names')->nullable();
            $table->date('event_date');
            $table->string('event_time')->nullable();
            $table->string('location')->nullable();
            $table->string('coordinator')->nullable();
            $table->text('ceremony_notes')->nullable();
            $table->string('status')->default('confirmed');
            $table->text('raw_description')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booked_jobs');
    }
};

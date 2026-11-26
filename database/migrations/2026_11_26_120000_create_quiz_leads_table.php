<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('email');
            $table->string('result', 32);
            $table->unsignedSmallInteger('score')->default(0);
            $table->json('answers');
            $table->string('wedding_date_text')->nullable();
            $table->string('venue_text')->nullable();
            $table->timestamp('consent_at');
            $table->string('consent_ip', 45)->nullable();
            $table->string('unsubscribe_token', 64)->unique();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('result_emailed_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_leads');
    }
};

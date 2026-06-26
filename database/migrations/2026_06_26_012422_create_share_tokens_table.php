<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public, revocable share link for a gallery or album. The opaque token is
     * the only credential needed to view the shared resource; access can be
     * time-limited and optionally password-protected.
     */
    public function up(): void
    {
        Schema::create('share_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->morphs('shareable');
            $table->timestamp('expires_at')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_tokens');
    }
};

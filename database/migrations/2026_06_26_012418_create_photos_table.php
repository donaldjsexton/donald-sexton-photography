<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Site-scoped photo assets for client galleries. Photos are deduplicated
     * per site by content hash, so an identical re-upload reuses the existing
     * row rather than expanding storage (mirrors the Java engine's hash-first
     * ingestion). EXIF is captured best-effort and never blocks ingestion.
     */
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();

            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('camera')->nullable();
            $table->string('lens')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->json('exif')->nullable();

            $table->timestamps();

            $table->unique(['site_id', 'sha256']);
            $table->index(['site_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};

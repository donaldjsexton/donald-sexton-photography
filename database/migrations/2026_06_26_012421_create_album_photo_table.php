<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot mapping site-scoped photos into albums, with manual ordering. A
     * photo may appear in multiple albums; it is unique once per album.
     */
    public function up(): void
    {
        Schema::create('album_photo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('added_at')->nullable();
            $table->timestamps();

            $table->unique(['album_id', 'photo_id']);
            $table->index(['site_id', 'album_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_photo');
    }
};

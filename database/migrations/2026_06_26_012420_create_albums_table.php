<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A sub-collection within a gallery (e.g. "Ceremony", "Reception"). Carries
     * its own visibility and cover photo; photos are attached via the
     * album_photo pivot.
     */
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('visibility', 20)->default('private');
            $table->foreignId('cover_photo_id')->nullable()->constrained('photos')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['site_id', 'gallery_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};

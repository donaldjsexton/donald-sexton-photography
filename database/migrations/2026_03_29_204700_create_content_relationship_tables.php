<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('category_journal_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'journal_post_id']);
        });

        Schema::create('journal_post_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['journal_post_id', 'tag_id']);
        });

        Schema::create('journal_post_venue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['journal_post_id', 'venue_id']);
        });

        Schema::create('tag_wedding_story', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wedding_story_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tag_id', 'wedding_story_id']);
        });

        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->morphs('mediable');
            $table->string('role')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['media_id', 'mediable_type', 'mediable_id']);
            $table->index(['mediable_type', 'mediable_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mediables');
        Schema::dropIfExists('tag_wedding_story');
        Schema::dropIfExists('journal_post_venue');
        Schema::dropIfExists('journal_post_tag');
        Schema::dropIfExists('category_journal_post');
    }
};

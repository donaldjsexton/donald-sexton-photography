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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('template')->default('custom');
            $table->string('status')->default('draft');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['template', 'status']);
            $table->index('published_at');
        });

        Schema::create('wedding_stories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status')->default('draft');
            $table->string('story_type')->default('wedding');
            $table->string('headline')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->date('event_date')->nullable();
            $table->string('location_name')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->json('client_names')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'story_type']);
            $table->index(['is_featured', 'display_order']);
            $table->index('published_at');
        });

        Schema::create('story_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_story_id')->constrained()->cascadeOnDelete();
            $table->string('block_type');
            $table->string('heading')->nullable();
            $table->longText('body')->nullable();
            $table->json('settings_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['wedding_story_id', 'sort_order']);
        });

        Schema::create('journal_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status')->default('draft');
            $table->string('post_type')->default('advice');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('original_wp_post_id')->nullable();
            $table->string('original_wp_url')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamps();

            $table->index(['status', 'post_type']);
            $table->index('published_at');
            $table->index('original_wp_post_id');
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('primary_name');
            $table->string('partner_name')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('event_type')->default('wedding');
            $table->date('event_date')->nullable();
            $table->string('venue_name')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('location_city')->nullable();
            $table->string('guest_count_range')->nullable();
            $table->string('budget_range')->nullable();
            $table->json('coverage_interest')->nullable();
            $table->string('heard_about')->nullable();
            $table->longText('message')->nullable();
            $table->string('status')->default('new');
            $table->string('source')->default('site_form');
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('event_date');
            $table->index('source');
        });

        Schema::create('import_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->string('source_table');
            $table->unsignedBigInteger('source_id');
            $table->string('source_url')->nullable();
            $table->morphs('target');
            $table->timestamps();

            $table->unique(['import_run_id', 'source_table', 'source_id']);
            $table->index('source_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_mappings');
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('journal_posts');
        Schema::dropIfExists('story_blocks');
        Schema::dropIfExists('wedding_stories');
        Schema::dropIfExists('pages');
    }
};

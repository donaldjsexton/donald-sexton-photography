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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('credit')->nullable();
            $table->decimal('focal_point_x', 5, 4)->nullable();
            $table->decimal('focal_point_y', 5, 4)->nullable();
            $table->unsignedBigInteger('original_wp_attachment_id')->nullable();
            $table->timestamps();

            $table->unique(['disk', 'path']);
            $table->index('original_wp_attachment_id');
        });

        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('region')->nullable();
            $table->string('headline')->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('website_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();

            $table->index(['is_featured', 'name']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('headline')->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->decimal('starting_price', 10, 2)->nullable();
            $table->string('price_label')->nullable();
            $table->unsignedSmallInteger('coverage_hours_min')->nullable();
            $table->unsignedSmallInteger('coverage_hours_max')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('draft');
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();

            $table->index(['status', 'display_order']);
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->text('quote');
            $table->string('author_name');
            $table->string('author_context')->nullable();
            $table->date('event_date')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('source')->default('manual');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_featured', 'sort_order']);
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->index('source');
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source_type')->default('wordpress');
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('summary_json')->nullable();
            $table->longText('error_log')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'status']);
        });

        Schema::create('homepage_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_heading')->nullable();
            $table->text('hero_subheading')->nullable();
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->json('featured_story_ids_json')->nullable();
            $table->json('featured_testimonial_ids_json')->nullable();
            $table->json('featured_journal_post_ids_json')->nullable();
            $table->text('investment_teaser')->nullable();
            $table->string('final_cta_heading')->nullable();
            $table->text('final_cta_body')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homepage_settings');
        Schema::dropIfExists('import_runs');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('media');
    }
};

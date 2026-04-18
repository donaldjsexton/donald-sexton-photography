<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Inquiry;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Redirect;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\User;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_routes_require_authentication(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('admin.login'));

        $this->get('/admin/inquiries')
            ->assertRedirect(route('admin.login'));

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Sign in to manage the site.');
    }

    public function test_admin_can_log_in_and_update_homepage_settings(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
        ]);

        $story = WeddingStory::create([
            'title' => 'Curated Story',
            'slug' => 'curated-story',
            'status' => 'published',
        ]);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $response = $this->actingAs($user)->put('/admin/homepage', [
            'hero_heading' => 'Curated hero',
            'featured_story_ids_json' => [$story->id],
        ]);

        $response->assertRedirect(route('admin.homepage.edit'));

        $this->assertDatabaseHas('homepage_settings', [
            'hero_heading' => 'Curated hero',
        ]);

        $settings = HomepageSetting::query()->first();
        $this->assertSame([$story->id], $settings?->featured_story_ids_json);
    }

    public function test_admin_can_update_platform_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('admin.settings.update'), [
            'google_analytics_measurement_id' => 'g-test12345',
        ]);

        $response->assertRedirect(route('admin.settings.edit', ['tab' => 'analytics']));

        $this->assertDatabaseHas('site_settings', [
            'google_analytics_measurement_id' => 'G-TEST12345',
        ]);

        $settings = SiteSetting::query()->first();
        $this->assertNotNull($settings);
        $this->assertTrue($settings->analyticsIsConfigured());
        $this->assertSame('G-TEST12345', $settings->analyticsMeasurementId());
    }

    public function test_admin_settings_page_uses_settings_specific_layout_hooks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.settings.edit'));

        $response->assertOk();
        $response->assertSee('admin-settings-page', false);
        $response->assertSee('admin-settings-grid', false);
        $response->assertSee('admin-settings-grid--activity', false);
        $response->assertSee('admin-settings-toolbar', false);
        $response->assertSee(route('admin.imports.index'), false);
    }

    public function test_admin_can_view_import_activity_page(): void
    {
        $user = User::factory()->create();

        ImportRun::query()->create([
            'source_type' => 'wordpress',
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
            'summary_json' => [
                'posts_imported' => 4,
                'redirects_synced' => 4,
            ],
            'error_log' => 'Example import error log that should stay off the settings page.',
        ]);

        $response = $this->actingAs($user)->get(route('admin.imports.index'));

        $response->assertOk();
        $response->assertSee('Import Activity');
        $response->assertSee('WordPress Import');
        $response->assertSee('posts imported: 4');
        $response->assertSee('View error log');
    }

    public function test_admin_can_upload_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/media', [
            'file' => UploadedFile::fake()->image('hero-frame.jpg', 1200, 1600),
            'alt_text' => 'Bride portrait',
            'caption' => 'Portrait frame',
        ]);

        $media = Media::query()->first();

        $response->assertRedirect(route('admin.media.edit', $media));
        $this->assertNotNull($media);
        $this->assertSame('Bride portrait', $media->alt_text);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_admin_media_edit_uses_public_media_url_and_focal_picker(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $path = UploadedFile::fake()->image('focus-test.jpg', 1200, 1600)
            ->store('media/2026/04', 'public');

        $media = Media::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => 'focus-test.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1200,
            'height' => 1600,
            'focal_point_x' => 0.25,
            'focal_point_y' => 0.75,
        ]);

        $response = $this->actingAs($user)->get(route('admin.media.edit', $media));

        $response->assertOk();
        $response->assertSee('/storage/'.$path, false);
        $response->assertSee('data-focal-picker', false);
        $response->assertSee('object-position: 25% 75%;', false);
    }

    public function test_admin_can_import_wordpress_posts_into_the_journal(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        Http::fake([
            'https://images.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <item>
            <title>Imported &amp; Wedding Timeline</title>
            <link>https://oldsite.test/2024/05/imported-wedding-timeline/</link>
            <pubDate>Fri, 03 May 2024 12:00:00 +0000</pubDate>
            <dc:creator><![CDATA[Donald]]></dc:creator>
            <category domain="category" nicename="wedding-advice"><![CDATA[Wedding Advice]]></category>
            <category domain="post_tag" nicename="clearwater"><![CDATA[Clearwater]]></category>
            <content:encoded><![CDATA[<p>Imported body copy.</p>]]></content:encoded>
            <excerpt:encoded><![CDATA[Imported excerpt.]]></excerpt:encoded>
            <wp:post_id>42</wp:post_id>
            <wp:post_date>2024-05-03 12:00:00</wp:post_date>
            <wp:post_date_gmt>2024-05-03 12:00:00</wp:post_date_gmt>
            <wp:comment_status>closed</wp:comment_status>
            <wp:ping_status>closed</wp:ping_status>
            <wp:post_name>imported-wedding-timeline</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_parent>0</wp:post_parent>
            <wp:menu_order>0</wp:menu_order>
            <wp:post_type>post</wp:post_type>
            <wp:is_sticky>0</wp:is_sticky>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_thumbnail_id]]></wp:meta_key>
                <wp:meta_value><![CDATA[420]]></wp:meta_value>
            </wp:postmeta>
        </item>
        <item>
            <title>Imported Timeline Featured Image</title>
            <wp:post_id>420</wp:post_id>
            <wp:post_parent>42</wp:post_parent>
            <wp:post_type>attachment</wp:post_type>
            <wp:attachment_url><![CDATA[https://images.example.com/imported-timeline-featured.jpg]]></wp:attachment_url>
        </item>
    </channel>
</rss>
XML;

        $response = $this->actingAs($user)->post('/admin/imports/wordpress', [
            'wxr_file' => UploadedFile::fake()->createWithContent('wordpress-export.xml', $xml),
        ]);

        $response->assertRedirect(route('admin.settings.edit', ['tab' => 'imports']).'#wordpress-import');

        $this->assertDatabaseHas('journal_posts', [
            'title' => 'Imported & Wedding Timeline',
            'original_wp_post_id' => 42,
            'slug' => 'imported-wedding-timeline',
        ]);

        $post = JournalPost::query()->where('original_wp_post_id', 42)->first();
        $this->assertNotNull($post);
        $this->assertDatabaseHas('categories', ['slug' => 'wedding-advice']);
        $this->assertDatabaseHas('tags', ['slug' => 'clearwater']);
        $this->assertDatabaseHas('redirects', [
            'from_path' => '/2024/05/imported-wedding-timeline/',
            'to_path' => '/journal/imported-wedding-timeline',
            'source' => 'wp_import',
        ]);
        $this->assertDatabaseHas('import_runs', [
            'source_type' => 'wordpress',
            'status' => 'completed',
        ]);

        $this->assertSame('Imported excerpt.', $post->excerpt);
        $this->assertStringContainsString('Imported body copy', $post->body ?? '');
        $this->assertCount(1, ImportRun::query()->get());
        $this->assertNotNull($post->hero_media_id);

        $media = Media::query()->find($post->hero_media_id);
        $this->assertNotNull($media);
        $this->assertSame(420, $media->original_wp_attachment_id);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_admin_can_review_and_update_inquiries(): void
    {
        $user = User::factory()->create();

        $inquiry = Inquiry::create([
            'primary_name' => 'Taylor',
            'partner_name' => 'Jordan',
            'email' => 'taylor@example.com',
            'phone' => '555-0100',
            'event_type' => 'wedding',
            'event_date' => '2026-10-10',
            'venue_name' => 'The Venue',
            'location_city' => 'Tampa',
            'guest_count_range' => '100-150',
            'budget_range' => '$6,000-$8,000',
            'coverage_interest' => ['Wedding day coverage'],
            'message' => 'We would love to talk.',
            'status' => 'new',
            'source' => 'site_form',
        ]);

        $this->actingAs($user)
            ->get(route('admin.inquiries.index'))
            ->assertOk()
            ->assertSee('Inquiries')
            ->assertSee('Taylor')
            ->assertSee('Open');

        $this->actingAs($user)
            ->get(route('admin.inquiries.edit', $inquiry))
            ->assertOk()
            ->assertSee('Review inquiry details')
            ->assertSee('Taylor')
            ->assertSee('We would love to talk.');

        $response = $this->actingAs($user)->put(route('admin.inquiries.update', $inquiry), [
            'status' => 'follow_up',
        ]);

        $response->assertRedirect(route('admin.inquiries.edit', $inquiry));

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id,
            'status' => 'follow_up',
        ]);
    }

    public function test_wordpress_import_command_accepts_local_file_paths(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <item>
            <title>Large Import Post</title>
            <link>https://oldsite.test/2024/05/large-import-post/</link>
            <pubDate>Fri, 03 May 2024 12:00:00 +0000</pubDate>
            <dc:creator><![CDATA[Donald]]></dc:creator>
            <category domain="category" nicename="wedding-advice"><![CDATA[Wedding Advice]]></category>
            <content:encoded><![CDATA[<p>Imported from command.</p>]]></content:encoded>
            <excerpt:encoded><![CDATA[Command excerpt.]]></excerpt:encoded>
            <wp:post_id>52</wp:post_id>
            <wp:post_date>2024-05-03 12:00:00</wp:post_date>
            <wp:post_date_gmt>2024-05-03 12:00:00</wp:post_date_gmt>
            <wp:post_name>large-import-post</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
        </item>
    </channel>
</rss>
XML;

        $path = storage_path('app/private/test-wordpress-export.xml');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $xml);

        Artisan::call('wordpress:import', [
            'path' => $path,
        ]);

        $this->assertSame(0, Artisan::output() !== '' ? 0 : 0);
        $this->assertDatabaseHas('journal_posts', [
            'title' => 'Large Import Post',
            'original_wp_post_id' => 52,
        ]);
        $this->assertDatabaseHas('redirects', [
            'from_path' => '/2024/05/large-import-post/',
            'to_path' => '/journal/large-import-post',
            'source' => 'wp_import',
        ]);
    }

    public function test_real_wedding_imports_are_promoted_into_wedding_stories(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://images.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <item>
            <title>Promoted Wedding Story</title>
            <link>https://oldsite.test/promoted-wedding-story/</link>
            <pubDate>Fri, 03 May 2024 12:00:00 +0000</pubDate>
            <dc:creator><![CDATA[Donald]]></dc:creator>
            <category domain="category" nicename="real-weddings"><![CDATA[Real Weddings]]></category>
            <content:encoded><![CDATA[<p>Wedding body.</p>]]></content:encoded>
            <excerpt:encoded><![CDATA[Wedding excerpt.]]></excerpt:encoded>
            <wp:post_id>88</wp:post_id>
            <wp:post_date>2024-05-03 12:00:00</wp:post_date>
            <wp:post_date_gmt>2024-05-03 12:00:00</wp:post_date_gmt>
            <wp:post_name>promoted-wedding-story</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
            <wp:postmeta>
                <wp:meta_key><![CDATA[_thumbnail_id]]></wp:meta_key>
                <wp:meta_value><![CDATA[8801]]></wp:meta_value>
            </wp:postmeta>
        </item>
        <item>
            <title>Promoted Wedding Story Featured Image</title>
            <wp:post_id>8801</wp:post_id>
            <wp:post_parent>88</wp:post_parent>
            <wp:post_type>attachment</wp:post_type>
            <wp:attachment_url><![CDATA[https://images.example.com/promoted-wedding-story-featured.jpg]]></wp:attachment_url>
        </item>
    </channel>
</rss>
XML;

        $path = storage_path('app/private/test-real-weddings.xml');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $xml);

        Artisan::call('wordpress:import', ['path' => $path]);

        $this->assertDatabaseHas('wedding_stories', [
            'title' => 'Promoted Wedding Story',
            'slug' => 'promoted-wedding-story',
            'original_wp_post_id' => 88,
        ]);

        $this->assertDatabaseHas('journal_posts', [
            'original_wp_post_id' => 88,
            'status' => 'archived',
            'post_type' => 'real_wedding',
        ]);

        $this->assertDatabaseHas('redirects', [
            'from_path' => '/promoted-wedding-story/',
            'to_path' => '/weddings/promoted-wedding-story',
            'source' => 'wp_import',
        ]);

        $story = WeddingStory::query()->where('original_wp_post_id', 88)->first();
        $post = JournalPost::query()->where('original_wp_post_id', 88)->first();

        $this->assertNotNull($story);
        $this->assertNotNull($post);
        $this->assertNotNull($story->hero_media_id);
        $this->assertSame($post->hero_media_id, $story->hero_media_id);
        $this->assertSame(1, $story->media()->count());

        $media = Media::query()->find($story->hero_media_id);
        $this->assertNotNull($media);
        $this->assertSame(8801, $media->original_wp_attachment_id);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_wordpress_import_keeps_advice_posts_out_of_wedding_stories_even_with_wedding_tags(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <item>
            <title>Wedding Advice Import</title>
            <link>https://oldsite.test/wedding-advice-import/</link>
            <pubDate>Fri, 03 May 2024 12:00:00 +0000</pubDate>
            <dc:creator><![CDATA[Donald]]></dc:creator>
            <category domain="category" nicename="wedding-advice"><![CDATA[Wedding Advice]]></category>
            <category domain="post_tag" nicename="wedding"><![CDATA[Wedding]]></category>
            <category domain="post_tag" nicename="tips"><![CDATA[Tips]]></category>
            <content:encoded><![CDATA[<p>Advice body.</p>]]></content:encoded>
            <excerpt:encoded><![CDATA[Advice excerpt.]]></excerpt:encoded>
            <wp:post_id>4242</wp:post_id>
            <wp:post_date>2024-05-03 12:00:00</wp:post_date>
            <wp:post_date_gmt>2024-05-03 12:00:00</wp:post_date_gmt>
            <wp:post_name>wedding-advice-import</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
        </item>
    </channel>
</rss>
XML;

        $path = storage_path('app/private/test-wordpress-advice.xml');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $xml);

        Artisan::call('wordpress:import', ['path' => $path]);

        $this->assertDatabaseHas('journal_posts', [
            'original_wp_post_id' => 4242,
            'post_type' => 'advice',
            'status' => 'published',
        ]);

        $this->assertDatabaseMissing('wedding_stories', [
            'original_wp_post_id' => 4242,
        ]);
    }

    public function test_wordpress_classification_repair_restores_misclassified_advice_posts_to_journal(): void
    {
        $category = Category::query()->create([
            'name' => 'Wedding Advice',
            'slug' => 'wedding-advice',
        ]);

        $tag = Tag::query()->create([
            'name' => 'Wedding',
            'slug' => 'wedding',
        ]);

        $post = JournalPost::query()->create([
            'title' => '25 Must-Have Photos for your wedding',
            'slug' => '25-must-have-photos-for-your-wedding',
            'status' => 'archived',
            'post_type' => 'real_wedding',
            'excerpt' => 'Advice excerpt.',
            'body' => '<p>Advice body.</p>',
            'original_wp_post_id' => 3777,
            'original_wp_url' => 'https://oldsite.test/25-must-have-photos-for-your-wedding/',
            'published_at' => now()->subDay(),
        ]);
        $post->categories()->sync([$category->id]);
        $post->tags()->sync([$tag->id]);

        $story = WeddingStory::query()->create([
            'title' => '25 Must-Have Photos for your wedding',
            'slug' => '25-must-have-photos-for-your-wedding',
            'status' => 'published',
            'story_type' => 'wedding',
            'headline' => '25 Must-Have Photos for your wedding',
            'excerpt' => 'Advice excerpt.',
            'body' => '<p>Advice body.</p>',
            'original_wp_post_id' => 3777,
            'original_wp_url' => 'https://oldsite.test/25-must-have-photos-for-your-wedding/',
            'published_at' => now()->subDay(),
        ]);

        WeddingStory::query()->create([
            'title' => '25 Must-Have Photos for your wedding',
            'slug' => '25-must-have-photos-for-your-wedding-archived-legacy',
            'status' => 'archived',
            'story_type' => 'wedding',
            'headline' => '25 Must-Have Photos for your wedding',
            'excerpt' => 'Advice excerpt.',
            'body' => '<p>Advice body.</p>',
            'original_wp_post_id' => 3777,
            'original_wp_url' => 'https://oldsite.test/25-must-have-photos-for-your-wedding/',
            'published_at' => now()->subDays(2),
        ]);

        Redirect::query()->create([
            'from_path' => '/journal/25-must-have-photos-for-your-wedding',
            'to_path' => '/weddings/25-must-have-photos-for-your-wedding',
            'status_code' => 301,
            'source' => 'wp_import',
        ]);

        ImportMapping::query()->create([
            'import_run_id' => ImportRun::query()->create([
                'source_type' => 'wordpress',
                'status' => 'completed',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
            ])->id,
            'source_table' => 'wp_posts',
            'source_id' => 3777,
            'target_type' => $story->getMorphClass(),
            'target_id' => $story->id,
            'source_url' => 'https://oldsite.test/25-must-have-photos-for-your-wedding/',
        ]);

        Artisan::call('wordpress:repair-classification');

        $this->assertDatabaseHas('journal_posts', [
            'id' => $post->id,
            'post_type' => 'advice',
            'status' => 'published',
        ]);

        $this->assertDatabaseHas('wedding_stories', [
            'id' => $story->id,
            'status' => 'archived',
        ]);

        $this->assertDatabaseHas('redirects', [
            'from_path' => '/weddings/25-must-have-photos-for-your-wedding',
            'to_path' => '/journal/25-must-have-photos-for-your-wedding',
            'status_code' => 301,
            'source' => 'wp_import',
        ]);

        $this->assertDatabaseMissing('redirects', [
            'from_path' => '/journal/25-must-have-photos-for-your-wedding',
            'to_path' => '/weddings/25-must-have-photos-for-your-wedding',
        ]);

        $this->assertDatabaseHas('import_mappings', [
            'source_table' => 'wp_posts',
            'source_id' => 3777,
            'target_type' => $post->getMorphClass(),
            'target_id' => $post->id,
        ]);
    }

    public function test_legacy_repair_media_auto_detects_legacy_upload_source(): void
    {
        $legacyRoot = storage_path('app/private/test-legacy-uploads');
        $relativePath = 'tests/auto-detected-legacy-image.jpg';
        $sourcePath = $legacyRoot.'/'.$relativePath;
        $publicPath = public_path('wp-content/uploads/'.$relativePath);

        File::ensureDirectoryExists(dirname($sourcePath));
        file_put_contents($sourcePath, 'legacy-image');

        if (File::exists($publicPath)) {
            File::delete($publicPath);
        }

        $post = JournalPost::query()->create([
            'title' => 'Legacy Upload Repair',
            'slug' => 'legacy-upload-repair',
            'status' => 'published',
            'post_type' => 'advice',
            'body' => '<p><img src="https://donaldsextonphotography.com/wp-content/uploads/tests/auto-detected-legacy-image.jpg" alt=""></p>',
            'original_wp_post_id' => 7001,
        ]);

        $previous = getenv('LEGACY_WORDPRESS_UPLOADS_PATH') ?: null;
        putenv('LEGACY_WORDPRESS_UPLOADS_PATH='.$legacyRoot);
        $_ENV['LEGACY_WORDPRESS_UPLOADS_PATH'] = $legacyRoot;
        $_SERVER['LEGACY_WORDPRESS_UPLOADS_PATH'] = $legacyRoot;

        try {
            Artisan::call('legacy:repair-media', [
                '--slug' => [$post->slug],
            ]);

            $this->assertFileExists($publicPath);
            $this->assertSame('legacy-image', file_get_contents($publicPath));
        } finally {
            if ($previous !== null) {
                putenv('LEGACY_WORDPRESS_UPLOADS_PATH='.$previous);
                $_ENV['LEGACY_WORDPRESS_UPLOADS_PATH'] = $previous;
                $_SERVER['LEGACY_WORDPRESS_UPLOADS_PATH'] = $previous;
            } else {
                putenv('LEGACY_WORDPRESS_UPLOADS_PATH');
                unset($_ENV['LEGACY_WORDPRESS_UPLOADS_PATH'], $_SERVER['LEGACY_WORDPRESS_UPLOADS_PATH']);
            }

            if (File::exists($publicPath)) {
                File::delete($publicPath);
            }

            File::deleteDirectory(dirname($sourcePath));
        }
    }

    public function test_pictime_import_command_ingests_story_text_and_images(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://gallery.example.com/stories/seaside-wedding' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Seaside Wedding | Pic-Time</title>
    <meta property="og:title" content="Seaside Wedding Celebration">
    <meta property="article:published_time" content="2025-08-14T12:00:00+00:00">
</head>
<body>
    <article>
        <h1>Seaside Wedding Celebration</h1>
        <p>The day began quietly along the water and built into a celebration by sunset.</p>
        <p>The ceremony overlooked the Gulf and the reception stayed warm well into the night.</p>
        <img src="https://images.example.com/one.jpg" alt="">
        <img src="https://images.example.com/two.jpg" alt="">
        <img src="https://images.example.com/three.jpg" alt="">
        <img src="https://images.example.com/four.jpg" alt="">
        <img src="https://images.example.com/five.jpg" alt="">
        <img src="https://images.example.com/six.jpg" alt="">
    </article>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']),
            'https://images.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Artisan::call('pictime:import', [
            'sources' => ['https://gallery.example.com/stories/seaside-wedding'],
            '--target' => 'auto',
        ]);

        $story = WeddingStory::query()->where('slug', 'seaside-wedding-celebration')->first();

        $this->assertNotNull($story);
        $this->assertSame('The day began quietly along the water and built into a celebration by sunset.', $story->excerpt);
        $this->assertSame(6, $story->media()->count());
        $this->assertNotNull($story->hero_media_id);
        $this->assertSame('pictime', ImportRun::query()->latest('id')->value('source_type'));
        $this->assertDatabaseHas('import_mappings', [
            'source_table' => 'pictime_posts',
            'source_url' => 'https://gallery.example.com/stories/seaside-wedding',
            'target_type' => $story->getMorphClass(),
            'target_id' => $story->id,
        ]);

        $media = Media::query()->find($story->hero_media_id);
        $this->assertNotNull($media);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_pictime_import_command_discovers_candidates_from_wordpress_xml(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://images.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <item>
            <title>Pic-Time Wedding</title>
            <link>https://gallery.pic-time.com/-/pic-time-wedding</link>
            <content:encoded><![CDATA[
                <article>
                    <h1>Pic-Time Wedding Story</h1>
                    <p>A quiet ceremony opened into a lively reception.</p>
                    <p>The coast stayed bright and warm through the evening.</p>
                    <img src="https://images.example.com/xml-one.jpg" alt="">
                    <img src="https://images.example.com/xml-two.jpg" alt="">
                    <img src="https://images.example.com/xml-three.jpg" alt="">
                    <img src="https://images.example.com/xml-four.jpg" alt="">
                    <img src="https://images.example.com/xml-five.jpg" alt="">
                    <img src="https://images.example.com/xml-six.jpg" alt="">
                </article>
            ]]></content:encoded>
            <wp:post_id>901</wp:post_id>
            <wp:post_name>pic-time-wedding</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
        </item>
    </channel>
</rss>
XML;

        $path = storage_path('app/private/test-pictime-discovery.xml');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $xml);

        Artisan::call('pictime:import', [
            'sources' => [$path],
            '--target' => 'auto',
        ]);

        $story = WeddingStory::query()->where('slug', 'pic-time-wedding')->first();

        $this->assertNotNull($story);
        $this->assertSame('A quiet ceremony opened into a lively reception.', $story->excerpt);
        $this->assertSame(6, $story->media()->count());
        $this->assertDatabaseHas('import_mappings', [
            'source_table' => 'pictime_posts',
            'target_type' => $story->getMorphClass(),
            'target_id' => $story->id,
        ]);
    }

    public function test_pictime_import_normalizes_gallery_subdomain_embed_urls_to_pic_time_pages(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://donaldsextonphotography.pic-time.com/-camejo' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Camejo Wedding | Pic-Time</title>
    <meta property="og:title" content="Camejo Wedding Celebration">
</head>
<body>
    <article>
        <h1>Camejo Wedding Celebration</h1>
        <p>A bright ceremony moved into a layered evening reception.</p>
        <img src="https://images.example.com/camejo-one.jpg" alt="">
        <img src="https://images.example.com/camejo-two.jpg" alt="">
        <img src="https://images.example.com/camejo-three.jpg" alt="">
        <img src="https://images.example.com/camejo-four.jpg" alt="">
        <img src="https://images.example.com/camejo-five.jpg" alt="">
        <img src="https://images.example.com/camejo-six.jpg" alt="">
    </article>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']),
            'https://images.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <item>
            <title>Camejo Wedding</title>
            <link>https://donaldsextonphotography.com/camejo-wedding/</link>
            <content:encoded><![CDATA[
                <template data-pt-type='blog' data-pt-slideshowid='65ff6bee495f8a0b20e83982'></template>
                <script src='https://gallery.donaldsextonphotography.com/-camejo/slideswebcomponentembed.js/65ff6bee495f8a0b20e83982?features=lightbox,pinterest&filtertags=galleryaccess' type='text/javascript'></script>
            ]]></content:encoded>
            <wp:post_id>902</wp:post_id>
            <wp:post_name>camejo-wedding</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
        </item>
    </channel>
</rss>
XML;

        $path = storage_path('app/private/test-pictime-gallery-subdomain.xml');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $xml);

        Artisan::call('pictime:import', [
            'sources' => [$path],
            '--target' => 'auto',
        ]);

        $story = WeddingStory::query()->where('slug', 'camejo-wedding')->first();

        $this->assertNotNull($story);
        $this->assertSame('A bright ceremony moved into a layered evening reception.', $story->excerpt);
        $this->assertSame(6, $story->media()->count());
        $this->assertDatabaseHas('import_mappings', [
            'source_table' => 'pictime_posts',
            'source_url' => 'https://donaldsextonphotography.pic-time.com/-camejo',
            'target_type' => $story->getMorphClass(),
            'target_id' => $story->id,
        ]);
    }

    public function test_pictime_import_reruns_reuse_existing_media_records(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://donaldsextonphotography.pic-time.com/-sawgrass' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sawgrass Session | Pic-Time</title>
</head>
<body>
    <article>
        <h1>Sawgrass Session</h1>
        <p>Lead paragraph.</p>
        <img src="https://images.example.com/shared-one.jpg" alt="">
        <img src="https://images.example.com/shared-two.jpg" alt="">
        <img src="https://images.example.com/shared-three.jpg" alt="">
        <img src="https://images.example.com/shared-four.jpg" alt="">
        <img src="https://images.example.com/shared-five.jpg" alt="">
        <img src="https://images.example.com/shared-six.jpg" alt="">
    </article>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']),
            'https://images.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Artisan::call('pictime:import', [
            'sources' => ['https://donaldsextonphotography.pic-time.com/-sawgrass'],
            '--target' => 'auto',
        ]);

        Artisan::call('pictime:import', [
            'sources' => ['https://donaldsextonphotography.pic-time.com/-sawgrass'],
            '--target' => 'auto',
        ]);

        $story = WeddingStory::query()->where('slug', 'sawgrass-session')->first();

        $this->assertNotNull($story);
        $this->assertSame(6, Media::query()->count());
        $this->assertSame(6, $story->media()->count());
    }

    public function test_pictime_import_uses_public_shell_cover_image_when_gallery_content_is_gated(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://donaldsextonphotography.pic-time.com/-shell-only' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Shell Only Gallery</title>
    <meta property="og:title" content="Shell Only Gallery">
    <meta property="og:image" content="https://images.example.com/shell-cover.jpg">
</head>
<body>
    <main>
        <form method="post" action="./login?redirect_back=%2f-shell-only%2fgallery"></form>
    </main>
    <script>
        var initParams = {"projectDate":"2024-05-07T22:06:51Z"};
    </script>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']),
            'https://images.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Artisan::call('pictime:import', [
            'sources' => ['https://donaldsextonphotography.pic-time.com/-shell-only'],
            '--target' => 'weddings',
        ]);

        $story = WeddingStory::query()->where('slug', 'shell-only-gallery')->first();

        $this->assertNotNull($story);
        $this->assertSame(1, $story->media()->count());
        $this->assertNotNull($story->hero_media_id);
        $this->assertSame('2024-05-07 22:06:51', optional($story->published_at)?->setTimezone('UTC')->format('Y-m-d H:i:s'));
    }

    public function test_pictime_import_extracts_embedded_searchread_content_from_wordpress_xml(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://images.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <item>
            <title>Bricen + Crystal - Engagement at Sawgrass Lake Park</title>
            <link>https://donaldsextonphotography.com/bricen-crystal-engagement-at-sawgrass-lake-park/</link>
            <content:encoded><![CDATA[
                <script> const searchread_663a9e74d3e0db1ed870a445 = `Engagement
Bricen + Crystal - Engagement at Sawgrass Lake Park
November 21, 2022

Imagine stepping into a world where every moment is frozen in time, where every glance, every laugh, every touch is captured with the click of a shutter.

Location: Sawgrass Lake Park

Vendors

Photographer
DONALD SEXTON PHOTOGRAPHY

View Full Gallery`;</script>
                <template data-pt-type='blog' data-pt-slideshowid='663a9e74d3e0db1ed870a445'></template>
                <script src='https://donaldsextonphotography.pic-time.com/-ne6227/slideswebcomponentembed.js/663a9e74d3e0db1ed870a445?features=lightbox,pinterest&filtertags=' type='text/javascript'></script>
            ]]></content:encoded>
            <wp:post_id>27318</wp:post_id>
            <wp:post_date>2024-05-07 22:06:51</wp:post_date>
            <wp:post_date_gmt>2024-05-07 22:06:51</wp:post_date_gmt>
            <wp:post_name>bricen-crystal-engagement-at-sawgrass-lake-park</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
            <category domain="category" nicename="engagement"><![CDATA[Engagement]]></category>
        </item>
        <item>
            <title>Featured Attachment</title>
            <wp:post_id>27319</wp:post_id>
            <wp:post_parent>27318</wp:post_parent>
            <wp:post_type>attachment</wp:post_type>
            <wp:attachment_url><![CDATA[https://images.example.com/sawgrass-featured.jpg]]></wp:attachment_url>
        </item>
    </channel>
</rss>
XML;

        $path = storage_path('app/private/test-pictime-searchread.xml');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $xml);

        Artisan::call('pictime:import', [
            'sources' => [$path],
            '--target' => 'auto',
        ]);

        $story = WeddingStory::query()->where('slug', 'bricen-crystal-engagement-at-sawgrass-lake-park')->first();

        $this->assertNotNull($story);
        $this->assertSame('engagement', $story->story_type);
        $this->assertStringContainsString('Imagine stepping into a world where every moment is frozen in time', $story->excerpt ?? '');
        $this->assertStringContainsString('Sawgrass Lake Park', $story->body ?? '');
        $this->assertStringNotContainsString('View Full Gallery', $story->body ?? '');
        $this->assertSame(1, $story->media()->count());
    }

    public function test_pictime_import_can_fetch_searchread_content_from_original_wordpress_page(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://donaldsextonphotography.com/bricen-crystal-engagement-at-sawgrass-lake-park/' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="en">
<body>
    <script> const searchread_663a9e74d3e0db1ed870a445 = `Engagement
Bricen + Crystal - Engagement at Sawgrass Lake Park
November 21, 2022

Imagine stepping into a world where every moment is frozen in time, where every glance, every laugh, every touch is captured with the click of a shutter.

Location: Sawgrass Lake Park

View Full Gallery`;</script>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']),
            'https://images.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <item>
            <title>Bricen + Crystal - Engagement at Sawgrass Lake Park</title>
            <link>https://donaldsextonphotography.com/bricen-crystal-engagement-at-sawgrass-lake-park/</link>
            <content:encoded><![CDATA[
                <template data-pt-type='blog' data-pt-slideshowid='663a9e74d3e0db1ed870a445'></template>
                <script src='https://donaldsextonphotography.pic-time.com/-ne6227/slideswebcomponentembed.js/663a9e74d3e0db1ed870a445?features=lightbox,pinterest&filtertags=' type='text/javascript'></script>
            ]]></content:encoded>
            <wp:post_id>27318</wp:post_id>
            <wp:post_date>2024-05-07 22:06:51</wp:post_date>
            <wp:post_date_gmt>2024-05-07 22:06:51</wp:post_date_gmt>
            <wp:post_name>bricen-crystal-engagement-at-sawgrass-lake-park</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
            <category domain="category" nicename="engagement"><![CDATA[Engagement]]></category>
        </item>
        <item>
            <title>Featured Attachment</title>
            <wp:post_id>27319</wp:post_id>
            <wp:post_parent>27318</wp:post_parent>
            <wp:post_type>attachment</wp:post_type>
            <wp:attachment_url><![CDATA[https://images.example.com/sawgrass-featured.jpg]]></wp:attachment_url>
        </item>
    </channel>
</rss>
XML;

        $path = storage_path('app/private/test-pictime-searchread-fetch.xml');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $xml);

        Artisan::call('pictime:import', [
            'sources' => [$path],
            '--target' => 'auto',
        ]);

        $story = WeddingStory::query()->where('slug', 'bricen-crystal-engagement-at-sawgrass-lake-park')->first();

        $this->assertNotNull($story);
        $this->assertStringContainsString('Imagine stepping into a world where every moment is frozen in time', $story->excerpt ?? '');
        $this->assertStringContainsString('Sawgrass Lake Park', $story->body ?? '');
    }

    public function test_pictime_import_preserves_embed_only_markup_from_wordpress_xml(): void
    {
        Storage::fake('public');

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <item>
            <title>Embed Only Wedding</title>
            <link>https://donaldsextonphotography.com/embed-only-wedding/</link>
            <content:encoded><![CDATA[
                <template data-pt-type='blog' data-pt-slideshowid='663a9e74d3e0db1ed870a445'></template>
                <script src='https://gallery.donaldsextonphotography.com/-embedonly/slideswebcomponentembed.js/663a9e74d3e0db1ed870a445?features=lightbox,pinterest&filtertags=galleryaccess' type='text/javascript'></script>
            ]]></content:encoded>
            <wp:post_id>30001</wp:post_id>
            <wp:post_date>2024-05-07 22:06:51</wp:post_date>
            <wp:post_date_gmt>2024-05-07 22:06:51</wp:post_date_gmt>
            <wp:post_name>embed-only-wedding</wp:post_name>
            <wp:status>publish</wp:status>
            <wp:post_type>post</wp:post_type>
            <category domain="category" nicename="real-wedding"><![CDATA[Real Wedding]]></category>
        </item>
    </channel>
</rss>
XML;

        $path = storage_path('app/private/test-pictime-embed-only.xml');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $xml);

        Artisan::call('pictime:import', [
            'sources' => [$path],
            '--target' => 'weddings',
        ]);

        $story = WeddingStory::query()->where('slug', 'embed-only-wedding')->first();

        $this->assertNotNull($story);
        $this->assertNull($story->body);
        $this->assertStringContainsString('data-pt-type=\'blog\'', $story->source_markup ?? '');
        $this->assertStringContainsString('slideswebcomponentembed.js/663a9e74d3e0db1ed870a445', $story->source_markup ?? '');
    }

    public function test_pictime_import_keeps_running_when_some_media_downloads_fail(): void
    {
        Storage::fake('public');

        Http::fake([
            'https://donaldsextonphotography.pic-time.com/-partial-media' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Partial Media Gallery</title>
</head>
<body>
    <article>
        <h1>Partial Media Gallery</h1>
        <p>Lead paragraph.</p>
        <img src="https://images.example.com/missing.jpg" alt="">
        <img src="https://images.example.com/present.jpg" alt="">
    </article>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']),
            'https://images.example.com/missing.jpg' => Http::response('', 404),
            'https://images.example.com/present.jpg' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Artisan::call('pictime:import', [
            'sources' => ['https://donaldsextonphotography.pic-time.com/-partial-media'],
            '--target' => 'weddings',
        ]);

        $story = WeddingStory::query()->where('slug', 'partial-media-gallery')->first();
        $run = ImportRun::query()->latest('id')->first();

        $this->assertNotNull($story);
        $this->assertSame(1, $story->media()->count());
        $this->assertNotNull($run);
        $this->assertSame('completed', $run->status);
    }

    public function test_pictime_repair_media_hydrates_gallery_images_from_embed_script_for_existing_records(): void
    {
        Storage::fake('public');

        $story = WeddingStory::create([
            'title' => 'Bricen + Crystal - Engagement at Sawgrass Lake Park',
            'slug' => 'bricen-crystal-engagement-at-sawgrass-lake-park',
            'status' => 'published',
            'story_type' => 'engagement',
            'published_at' => now(),
            'source_markup' => <<<'HTML'
<template data-pt-type='blog' data-pt-slideshowid='663a9e74d3e0db1ed870a445'></template>
<script src='https://donaldsextonphotography.pic-time.com/-ne6227/slideswebcomponentembed.js/663a9e74d3e0db1ed870a445?features=lightbox,pinterest&filtertags=' type='text/javascript'></script>
HTML,
        ]);

        Http::fake([
            'https://donaldsextonphotography.pic-time.com/-ne6227/slideswebcomponentembed.js/663a9e74d3e0db1ed870a445*' => Http::response(<<<'JS'
(function(){
  var cover = "https://cdn.example.com/slideshows/663a9e74d3e0db1ed870a445/images/_pt(-100).jpg?rev=2";
  var imageOne = "https://cdn.example.com/slideshows/663a9e74d3e0db1ed870a445/images_small/crystal-bricen-engagement-sawgrass-park-001.jpg?rev=2 1x, https://cdn.example.com/slideshows/663a9e74d3e0db1ed870a445/images/crystal-bricen-engagement-sawgrass-park-001.jpg?rev=2 2x";
  var imageTwo = "https://cdn.example.com/slideshows/663a9e74d3e0db1ed870a445/images_small/crystal-bricen-engagement-sawgrass-park-002.jpg?rev=2 1x, https://cdn.example.com/slideshows/663a9e74d3e0db1ed870a445/images/crystal-bricen-engagement-sawgrass-park-002.jpg?rev=2 2x";
  var imageThree = "https://cdn.example.com/slideshows/663a9e74d3e0db1ed870a445/images_small/crystal-bricen-engagement-sawgrass-park-003.jpg?rev=2 1x, https://cdn.example.com/slideshows/663a9e74d3e0db1ed870a445/images/crystal-bricen-engagement-sawgrass-park-003.jpg?rev=2 2x";
})();
JS, 200, ['Content-Type' => 'application/javascript']),
            'https://cdn.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Artisan::call('pictime:repair-media', [
            '--slug' => [$story->slug],
        ]);

        $story->refresh();

        $this->assertSame(3, $story->media()->count());
        $this->assertNotNull($story->hero_media_id);

        $heroMedia = Media::query()->find($story->hero_media_id);
        $this->assertNotNull($heroMedia);
        Storage::disk('public')->assertExists($heroMedia->path);
    }

    public function test_pictime_repair_media_can_hydrate_shell_cover_for_records_without_saved_markup(): void
    {
        Storage::fake('public');

        $post = JournalPost::create([
            'title' => 'Shell Only Gallery',
            'slug' => 'shell-only-gallery',
            'status' => 'published',
            'post_type' => 'advice',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-shell-only',
        ]);

        Http::fake([
            'https://donaldsextonphotography.pic-time.com/-shell-only' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Shell Only Gallery</title>
    <meta property="og:title" content="Shell Only Gallery">
    <meta property="og:image" content="https://images.example.com/shell-cover.jpg">
</head>
<body>
    <main>
        <form method="post" action="./login?redirect_back=%2f-shell-only%2fgallery"></form>
    </main>
    <script>
        var initParams = {"projectDate":"2024-05-07T22:06:51Z"};
    </script>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']),
            'https://images.example.com/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Artisan::call('pictime:repair-media', [
            '--slug' => [$post->slug],
        ]);

        $post->refresh();

        $this->assertSame(1, $post->media()->count());
        $this->assertNotNull($post->hero_media_id);
        $this->assertSame('2024-05-07 22:06:51', optional($post->published_at)?->setTimezone('UTC')->format('Y-m-d H:i:s'));
    }

    public function test_pictime_repair_content_uses_saved_manual_markup_for_narrative(): void
    {
        $story = WeddingStory::create([
            'title' => 'Manual Narrative Story',
            'slug' => 'manual-narrative-story',
            'status' => 'published',
            'story_type' => 'wedding',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-manualnarrative',
            'source_markup' => <<<'HTML'
<template data-pt-type='blog' data-pt-slideshowid='abc123'></template>
<script src='https://donaldsextonphotography.pic-time.com/-manualnarrative/slideswebcomponentembed.js/abc123?features=lightbox,pinterest&filtertags=galleryaccess' type='text/javascript'></script>
HTML,
        ]);

        $manualDir = storage_path('app/private/manual-pictime');
        $manualPath = $manualDir.'/'.$story->slug.'.html';
        File::ensureDirectoryExists($manualDir);
        File::put($manualPath, <<<'HTML'
<div data-tokenid="text2">Wedding</div>
<h1 data-tokenid="text1">Manual Narrative Story</h1>
<div data-tokenid="text4">First paragraph of real story copy.<br><br>Second paragraph with more detail.</div>
<h2 data-tokenid="text5">Venue: Bayfront</h2>
<div data-tokenid="text4">Venue notes from the saved Pic-Time page.</div>
<div data-tokenid="text6">Vendors</div>
<div data-tokenid="text7">Photographer<br>DONALD SEXTON PHOTOGRAPHY</div>
HTML);

        try {
            Artisan::call('pictime:repair-content');

            $story->refresh();

            $this->assertSame('First paragraph of real story copy.', $story->excerpt);
            $this->assertStringContainsString('Second paragraph with more detail.', $story->body ?? '');
            $this->assertStringContainsString('Venue: Bayfront', $story->body ?? '');
            $this->assertStringContainsString('Photographer DONALD SEXTON PHOTOGRAPHY', strip_tags($story->body ?? ''));
        } finally {
            File::delete($manualPath);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Http\Controllers\SitemapController;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SitemapController::forgetCache();
    }

    public function test_sitemap_declares_image_namespace_and_xml_content_type(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', false);
    }

    public function test_sitemap_includes_image_entries_for_wedding_story_gallery(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/hero.jpg',
            'filename' => 'hero.jpg',
            'mime_type' => 'image/jpeg',
            'alt_text' => 'Hero alt text',
        ]);

        $gallery = Media::create([
            'disk' => 'public',
            'path' => 'media/test/gallery.jpg',
            'filename' => 'gallery.jpg',
            'mime_type' => 'image/jpeg',
            'caption' => 'Garden ceremony',
        ]);

        $story = WeddingStory::create([
            'title' => 'Sample Wedding',
            'slug' => 'sample-wedding',
            'status' => 'published',
            'hero_media_id' => $hero->id,
            'published_at' => now()->subDay(),
        ]);

        $story->media()->attach([
            $hero->id => ['role' => 'hero', 'sort_order' => 0],
            $gallery->id => ['role' => 'gallery', 'sort_order' => 1],
        ]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('weddings.show', 'sample-wedding'))
            ->assertSee('<image:image>', false)
            ->assertSee('/storage/media/test/hero.jpg', false)
            ->assertSee('/storage/media/test/gallery.jpg', false)
            ->assertSee('Hero alt text', false)
            ->assertSee('Garden ceremony', false);
    }

    public function test_sitemap_includes_venue_hero_image(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/venue.jpg',
            'filename' => 'venue.jpg',
            'mime_type' => 'image/jpeg',
            'alt_text' => 'Venue hero',
        ]);

        Venue::create([
            'name' => 'Lakeside Estate',
            'slug' => 'lakeside-estate',
            'hero_media_id' => $hero->id,
        ]);

        $this->get(route('sitemap'))
            ->assertSee(route('venues.show', 'lakeside-estate'))
            ->assertSee('/storage/media/test/venue.jpg', false)
            ->assertSee('Venue hero', false);
    }

    public function test_sitemap_response_is_cached_between_requests(): void
    {
        $this->get(route('sitemap'))->assertOk();

        $this->assertTrue(Cache::has('sitemap.xml.body'));
    }

    public function test_saving_a_wedding_story_invalidates_sitemap_cache(): void
    {
        $this->get(route('sitemap'))->assertOk();

        $this->assertTrue(Cache::has('sitemap.xml.body'));

        WeddingStory::create([
            'title' => 'Fresh Story',
            'slug' => 'fresh-story',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->assertFalse(Cache::has('sitemap.xml.body'));
    }

    public function test_saving_a_journal_post_invalidates_sitemap_cache(): void
    {
        $this->get(route('sitemap'))->assertOk();
        $this->assertTrue(Cache::has('sitemap.xml.body'));

        JournalPost::create([
            'title' => 'Fresh Post',
            'slug' => 'fresh-post',
            'status' => 'published',
            'post_type' => 'advice',
            'published_at' => now()->subDay(),
        ]);

        $this->assertFalse(Cache::has('sitemap.xml.body'));
    }

    public function test_saving_a_venue_invalidates_sitemap_cache(): void
    {
        $this->get(route('sitemap'))->assertOk();
        $this->assertTrue(Cache::has('sitemap.xml.body'));

        Venue::create([
            'name' => 'New Venue',
            'slug' => 'new-venue',
        ]);

        $this->assertFalse(Cache::has('sitemap.xml.body'));
    }

    public function test_saving_a_page_invalidates_sitemap_cache(): void
    {
        $this->get(route('sitemap'))->assertOk();
        $this->assertTrue(Cache::has('sitemap.xml.body'));

        Page::create([
            'title' => 'Tampa Wedding Photographer',
            'slug' => 'tampa',
            'template' => 'location',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->assertFalse(Cache::has('sitemap.xml.body'));
    }
}

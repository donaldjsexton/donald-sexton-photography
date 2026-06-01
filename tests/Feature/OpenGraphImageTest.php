<?php

namespace Tests\Feature;

use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpenGraphImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_wedding_story_og_route_returns_a_png(): void
    {
        $story = WeddingStory::create([
            'title' => 'Sarah and Michael at the Beach',
            'slug' => 'sarah-michael-beach',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get(route('og.story', $story->slug));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
    }

    public function test_journal_post_og_route_returns_a_png(): void
    {
        $post = JournalPost::create([
            'title' => 'Planning Notes',
            'slug' => 'planning-notes',
            'status' => 'published',
            'post_type' => 'advice',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('og.journal', $post->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_venue_og_route_returns_a_png(): void
    {
        $venue = Venue::create([
            'name' => 'Lakeside Estate',
            'slug' => 'lakeside-estate',
            'city' => 'Clearwater',
            'state' => 'FL',
        ]);

        $this->get(route('og.venue', $venue->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_og_route_returns_404_for_unknown_slug(): void
    {
        $this->get(route('og.story', 'does-not-exist'))->assertNotFound();
        $this->get(route('og.journal', 'does-not-exist'))->assertNotFound();
        $this->get(route('og.venue', 'does-not-exist'))->assertNotFound();
    }

    public function test_og_route_caches_image_on_disk_after_first_request(): void
    {
        $story = WeddingStory::create([
            'title' => 'A Story',
            'slug' => 'a-story',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $disk = Storage::disk('public');

        $this->assertEmpty($disk->files('og/wedding-stories'));

        $this->get(route('og.story', $story->slug))->assertOk();

        $this->assertNotEmpty($disk->files('og/wedding-stories'));
    }

    public function test_wedding_story_show_view_references_generated_og_image(): void
    {
        $story = WeddingStory::create([
            'title' => 'Sarah and Michael',
            'slug' => 'sarah-and-michael',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee(route('og.story', $story->slug), false);
    }

    public function test_og_card_includes_hero_image_when_present(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/og-hero.jpg',
            'filename' => 'og-hero.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $bytes = $this->fakeJpeg(800, 600);
        Storage::disk('public')->put($hero->path, $bytes);

        $story = WeddingStory::create([
            'title' => 'With Hero',
            'slug' => 'with-hero',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'hero_media_id' => $hero->id,
        ]);

        $this->get(route('og.story', $story->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_oversized_original_is_skipped_and_renders_text_only_card(): void
    {
        $textOnly = $this->renderStoryCard('plain', 'Same Title');

        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/huge.jpg',
            'filename' => 'huge.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        // Longest edge above the decode ceiling but cheap to allocate.
        Storage::disk('public')->put($hero->path, $this->fakeJpeg(3200, 100));

        $withOversized = $this->renderStoryCard('huge', 'Same Title', $hero);

        $this->assertSame(
            $textOnly,
            $withOversized,
            'An oversized original must be skipped, yielding the same text-only card.'
        );
    }

    public function test_resized_webp_variant_is_used_when_original_is_oversized(): void
    {
        if (! function_exists('imagecreatefromwebp') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('WebP support is unavailable in this PHP build.');
        }

        $textOnly = $this->renderStoryCard('plain-variant', 'Same Title');

        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/source.jpg',
            'filename' => 'source.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        // The original on disk is oversized and would be skipped on its own...
        Storage::disk('public')->put($hero->path, $this->fakeJpeg(3200, 100));
        // ...but a bounded WebP derivative exists and must be preferred.
        Storage::disk('public')->put('media/test/source-1600.webp', $this->fakeWebp(800, 600));

        $withVariant = $this->renderStoryCard('with-variant', 'Same Title', $hero);

        $this->assertNotSame(
            $textOnly,
            $withVariant,
            'A bounded WebP variant must be decoded even when the original is oversized.'
        );
    }

    private function renderStoryCard(string $slug, string $title, ?Media $heroMedia = null): string
    {
        $story = WeddingStory::create([
            'title' => $title,
            'slug' => $slug,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'hero_media_id' => $heroMedia?->id,
        ]);

        $response = $this->get(route('og.story', $story->slug));
        $response->assertOk();

        return $response->getContent();
    }

    private function fakeJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, (int) imagecolorallocate($image, 200, 180, 160));

        ob_start();
        imagejpeg($image, null, 80);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function fakeWebp(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, (int) imagecolorallocate($image, 120, 90, 200));

        ob_start();
        imagewebp($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}

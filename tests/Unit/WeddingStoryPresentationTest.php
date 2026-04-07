<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Models\WeddingStory;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WeddingStoryPresentationTest extends TestCase
{
    public function test_imported_wordpress_story_is_split_into_lead_gallery_and_body(): void
    {
        $story = new WeddingStory([
            'title' => 'Imported Story',
            'excerpt' => 'Shortened import excerpt...',
            'body' => <<<'HTML'
<p>Some weddings are beautiful, and some weddings are <em>alive.</em><br>Christopher and Jennifer’s day felt like a celebration from the moment it began.</p>
<figure class="wp-import-gallery">
    <figure class="wp-import-image"><img src="https://example.test/one.jpg" alt=""></figure>
    <figure class="wp-import-image"><img src="https://example.test/two.jpg" alt=""></figure>
</figure>
<p>This is the closing paragraph that should remain in the reading section.</p>
HTML,
            'original_wp_post_id' => 40433,
        ]);

        $presentation = $story->presentationContent();

        $this->assertSame(
            'Some weddings are beautiful, and some weddings are alive. Christopher and Jennifer’s day felt like a celebration from the moment it began.',
            $presentation['hero_copy']
        );
        $this->assertNotNull($presentation['gallery_html']);
        $this->assertStringContainsString('wp-import-gallery', $presentation['gallery_html']);
        $this->assertStringContainsString('loading="lazy"', $presentation['gallery_html']);
        $this->assertStringContainsString('decoding="async"', $presentation['gallery_html']);
        $this->assertNotNull($presentation['body_html']);
        $this->assertStringContainsString('closing paragraph', $presentation['body_html']);
        $this->assertStringNotContainsString('Some weddings are beautiful', $presentation['body_html']);
    }

    public function test_featured_image_url_falls_back_to_first_imported_body_image(): void
    {
        $story = new WeddingStory([
            'body' => <<<'HTML'
<p>Lead paragraph.</p>
<figure class="wp-import-gallery">
    <figure class="wp-import-image"><img src="https://example.test/imported-featured-image.jpg" alt=""></figure>
</figure>
HTML,
            'original_wp_post_id' => 505,
        ]);

        $this->assertSame(
            'https://example.test/imported-featured-image.jpg',
            $story->featuredImageUrl()
        );
    }

    public function test_featured_image_url_uses_relative_storage_path_for_local_media(): void
    {
        $story = new WeddingStory([
            'hero_media_id' => 1,
        ]);

        $story->setRelation('heroMedia', new Media([
            'disk' => 'public',
            'path' => 'imports/pictime/sample/hero.jpg',
            'filename' => 'hero.jpg',
        ]));

        $this->assertSame(
            '/storage/imports/pictime/sample/hero.jpg',
            $story->featuredImageUrl()
        );
    }

    public function test_featured_image_url_ignores_legacy_wordpress_uploads(): void
    {
        config()->set('app.url', 'https://donaldsextonphotography.com');

        $story = new WeddingStory([
            'body' => <<<'HTML'
<p>Lead paragraph.</p>
<figure class="wp-import-gallery">
    <figure class="wp-import-image"><img src="https://donaldsextonphotography.com/wp-content/uploads/2025/11/legacy-image-scaled.jpg" alt=""></figure>
</figure>
<p>Closing copy.</p>
HTML,
            'original_wp_post_id' => 40433,
        ]);

        $presentation = $story->presentationContent();

        $this->assertNull($story->featuredImageUrl());
        $this->assertNull($presentation['gallery_html']);
        $this->assertSame('<p>Closing copy.</p>', trim((string) $presentation['body_html']));
        $this->assertStringNotContainsString('wp-content/uploads', (string) $story->sanitizedBody());
    }

    public function test_featured_image_url_allows_mirrored_legacy_wordpress_uploads(): void
    {
        config()->set('app.url', 'https://donaldsextonphotography.com');

        $relativePath = 'wp-content/uploads/tests/legacy-image-scaled.jpg';
        $absolutePath = public_path($relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, 'image-bytes');

        try {
            $story = new WeddingStory([
                'body' => <<<'HTML'
<p>Lead paragraph.</p>
<figure class="wp-import-gallery">
    <figure class="wp-import-image"><img src="https://donaldsextonphotography.com/wp-content/uploads/tests/legacy-image-scaled.jpg" alt=""></figure>
</figure>
HTML,
                'original_wp_post_id' => 40434,
            ]);

            $this->assertSame(
                'https://donaldsextonphotography.com/wp-content/uploads/tests/legacy-image-scaled.jpg',
                $story->featuredImageUrl()
            );
            $this->assertStringContainsString('/wp-content/uploads/tests/legacy-image-scaled.jpg', (string) $story->sanitizedBody());
        } finally {
            File::delete($absolutePath);
        }
    }
}

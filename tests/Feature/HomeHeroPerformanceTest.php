<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeHeroPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_hero_image_loads_eagerly_at_high_priority(): void
    {
        // The hero image is the LCP element. It must render eagerly at high
        // priority rather than lazily — a lazily loaded LCP image is a
        // PageSpeed regression that undoes the matching <link rel=preload>.
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/lead-hero.jpg',
            'filename' => 'lead-hero.jpg',
            'mime_type' => 'image/jpeg',
            'alt_text' => 'Lead hero alt text',
            'width' => 1600,
            'height' => 2000,
        ]);

        WeddingStory::create([
            'title' => 'Lead Wedding',
            'slug' => 'lead-wedding',
            'status' => 'published',
            'hero_media_id' => $hero->id,
            'published_at' => now()->subDay(),
        ]);

        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<img[^>]*loading="eager"[^>]*fetchpriority="high"|<img[^>]*fetchpriority="high"[^>]*loading="eager"/',
            $content,
            'The lead hero image must render with loading="eager" and fetchpriority="high".',
        );
    }

    public function test_font_stylesheet_is_loaded_without_blocking_first_paint(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('rel="preload" as="style" href="https://fonts.bunny.net', $content);
        $this->assertStringContainsString('this.rel=\'stylesheet\'', $content);
        $this->assertStringContainsString('display=swap', $content);
    }

    public function test_analytics_script_is_deferred_off_the_critical_path(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST12345']);

        $content = $this->get(route('home'))->assertOk()->getContent();

        // The queue is set up inline, but the gtag.js download must be deferred
        // rather than loaded with a render-time <script async src> tag.
        $this->assertStringContainsString("gtag('config', 'G-TEST12345')", $content);
        $this->assertStringContainsString('requestIdleCallback', $content);
        $this->assertStringNotContainsString('<script async src="https://www.googletagmanager.com/gtag/js', $content);
    }
}

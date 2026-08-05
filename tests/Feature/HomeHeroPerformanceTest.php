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
}

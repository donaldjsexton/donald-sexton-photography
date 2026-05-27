<?php

namespace Tests\Feature;

use App\Models\JournalPost;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_wedding_story_renders_visible_breadcrumbs(): void
    {
        $story = WeddingStory::create([
            'title' => 'Sarah & Michael',
            'slug' => 'sarah-and-michael',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get(route('weddings.show', $story->slug));

        $response->assertOk()
            ->assertSee('<nav class="breadcrumbs"', false)
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('href="'.route('weddings.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Sarah &amp; Michael', false);
    }

    public function test_journal_post_renders_visible_breadcrumbs(): void
    {
        $post = JournalPost::create([
            'title' => 'Planning Notes',
            'slug' => 'planning-notes',
            'status' => 'published',
            'post_type' => 'advice',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('journal.show', $post->slug))
            ->assertOk()
            ->assertSee('<nav class="breadcrumbs"', false)
            ->assertSee('href="'.route('journal.index').'"', false)
            ->assertSee('Planning Notes', false);
    }

    public function test_venue_show_renders_visible_breadcrumbs(): void
    {
        $venue = Venue::create([
            'name' => 'Lakeside Estate',
            'slug' => 'lakeside-estate',
        ]);

        $this->get(route('venues.show', $venue->slug))
            ->assertOk()
            ->assertSee('<nav class="breadcrumbs"', false)
            ->assertSee('href="'.route('venues.index').'"', false)
            ->assertSee('Lakeside Estate', false);
    }
}

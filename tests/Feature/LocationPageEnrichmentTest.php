<?php

namespace Tests\Feature;

use App\Models\JournalPost;
use App\Models\Page;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationPageEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_page_lists_venues_in_the_same_city(): void
    {
        Page::create([
            'title' => 'Tampa Wedding Photographer',
            'slug' => 'tampa',
            'template' => 'location',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $matching = Venue::create([
            'name' => 'Tampa Bay Hotel',
            'slug' => 'tampa-bay-hotel',
            'city' => 'Tampa',
            'state' => 'FL',
        ]);

        $other = Venue::create([
            'name' => 'Sarasota Garden',
            'slug' => 'sarasota-garden',
            'city' => 'Sarasota',
            'state' => 'FL',
        ]);

        $this->get(route('pages.location', 'tampa'))
            ->assertOk()
            ->assertSee($matching->name)
            ->assertSee(route('venues.show', $matching->slug))
            ->assertDontSee($other->name);
    }

    public function test_location_page_lists_wedding_stories_in_the_same_city(): void
    {
        Page::create([
            'title' => 'Clearwater Wedding Photographer',
            'slug' => 'clearwater',
            'template' => 'location',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $matching = WeddingStory::create([
            'title' => 'Sarah and Michael at the Beach',
            'slug' => 'sarah-michael-clearwater',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'city' => 'Clearwater',
            'state' => 'FL',
        ]);

        $other = WeddingStory::create([
            'title' => 'Other Wedding',
            'slug' => 'other-wedding',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'city' => 'Miami',
            'state' => 'FL',
        ]);

        $this->get(route('pages.location', 'clearwater'))
            ->assertOk()
            ->assertSee($matching->title)
            ->assertDontSee($other->title);
    }

    public function test_location_page_lists_journal_posts_linked_to_venues_in_the_area(): void
    {
        Page::create([
            'title' => 'St Petersburg Wedding Photographer',
            'slug' => 'st-petersburg',
            'template' => 'location',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $venueInCity = Venue::create([
            'name' => 'Vinoy Park',
            'slug' => 'vinoy-park',
            'city' => 'St Petersburg',
            'state' => 'FL',
        ]);

        $post = JournalPost::create([
            'title' => 'Top venues in this city',
            'slug' => 'top-venues',
            'status' => 'published',
            'post_type' => 'venue_spotlight',
            'published_at' => now()->subDay(),
        ]);

        $post->venues()->attach($venueInCity->id);

        $this->get(route('pages.location', 'st-petersburg'))
            ->assertOk()
            ->assertSee('Top venues in this city');
    }

    public function test_location_page_renders_breadcrumbs(): void
    {
        Page::create([
            'title' => 'Tampa Wedding Photographer',
            'slug' => 'tampa',
            'template' => 'location',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('pages.location', 'tampa'))
            ->assertOk()
            ->assertSee('<nav class="breadcrumbs"', false)
            ->assertSee('Tampa Wedding Photographer', false);
    }
}

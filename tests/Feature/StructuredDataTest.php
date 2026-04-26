<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_venue_show_renders_place_schema_with_address_and_external_links(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/venue-hero.jpg',
            'filename' => 'venue-hero.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $venue = Venue::create([
            'name' => 'Knotted Roots on the Lake',
            'slug' => 'knotted-roots-on-the-lake',
            'summary' => 'A garden venue tucked beside a quiet lake.',
            'city' => 'Land O Lakes',
            'state' => 'FL',
            'website_url' => 'https://knottedroots.example.com',
            'google_places_id' => 'ChIJTESTPLACEID',
            'hero_media_id' => $hero->id,
        ]);

        $this->get(route('venues.show', $venue->slug))
            ->assertOk()
            ->assertSee('"@type":"Place"', false)
            ->assertSee('"name":"Knotted Roots on the Lake"', false)
            ->assertSee('"addressLocality":"Land O Lakes"', false)
            ->assertSee('"addressRegion":"FL"', false)
            ->assertSee('https://knottedroots.example.com', false)
            ->assertSee('place_id:ChIJTESTPLACEID', false);
    }

    public function test_wedding_story_renders_image_gallery_schema_linked_to_venue_place(): void
    {
        $venue = Venue::create([
            'name' => 'Powel Crosley Estate',
            'slug' => 'powel-crosley-estate',
            'city' => 'Sarasota',
            'state' => 'FL',
        ]);

        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/story-hero.jpg',
            'filename' => 'story-hero.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $gallery = Media::create([
            'disk' => 'public',
            'path' => 'media/test/story-gallery.jpg',
            'filename' => 'story-gallery.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $story = WeddingStory::create([
            'title' => 'Sarah & Michael at Powel Crosley',
            'slug' => 'sarah-and-michael-powel-crosley',
            'status' => 'published',
            'venue_id' => $venue->id,
            'event_date' => '2027-03-14',
            'client_names' => ['Sarah', 'Michael'],
            'hero_media_id' => $hero->id,
            'published_at' => now()->subDay(),
        ]);

        $story->media()->attach([
            $hero->id => ['role' => 'hero', 'sort_order' => 0],
            $gallery->id => ['role' => 'gallery', 'sort_order' => 1],
        ]);

        $venuePlaceId = route('venues.show', $venue->slug).'#place';

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('"@type":"ImageGallery"', false)
            ->assertSee('"@type":"Place","@id":"'.$venuePlaceId.'"', false)
            ->assertSee('"contentLocation":{"@id":"'.$venuePlaceId.'"}', false)
            ->assertSee('"startDate":"2027-03-14"', false);
    }
}

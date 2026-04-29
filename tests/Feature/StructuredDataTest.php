<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\Venue;
use App\Models\WeddingStory;
use App\Services\GoogleBusinessProfile;
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
            ->assertSee('"startDate":"2027-03-14"', false)
            ->assertSee('"@type":"Photograph"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_journal_post_renders_breadcrumb_list_and_blog_is_part_of(): void
    {
        $post = JournalPost::create([
            'title' => 'Spring Planning Notes',
            'slug' => 'spring-planning-notes',
            'status' => 'published',
            'post_type' => 'advice',
            'excerpt' => 'A short excerpt.',
            'body' => '<p>Body.</p>',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('journal.show', $post->slug))
            ->assertOk()
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"@type":"Blog"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"name":"Journal"', false)
            ->assertSee('"name":"Spring Planning Notes"', false);
    }

    public function test_collections_index_emits_offer_catalog_when_collections_exist(): void
    {
        Collection::create([
            'name' => 'Essential Coverage',
            'slug' => 'essential-coverage',
            'status' => 'published',
            'summary' => 'Six hours of full day coverage.',
            'starting_price' => 3800,
            'display_order' => 1,
        ]);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertSee('"@type":"OfferCatalog"', false)
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('"price":"3800.00"', false)
            ->assertSee('"priceCurrency":"USD"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_inquiry_page_renders_faq_page_schema(): void
    {
        $this->get(route('inquiry.create'))
            ->assertOk()
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"Question"', false)
            ->assertSee('"@type":"Answer"', false)
            ->assertSee('Where are you based and how far do you travel?', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_homepage_organization_schema_advertises_multiple_service_offerings(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('"@type":"WeddingPhotographer"', false)
            ->assertSee('"name":"Wedding Photography"', false)
            ->assertSee('"name":"Engagement Photography"', false)
            ->assertSee('"name":"Elopement Photography"', false);
    }

    public function test_organization_schema_includes_same_as_from_settings(): void
    {
        SiteSetting::create([
            'instagram_url' => 'https://instagram.com/donaldsextonphoto',
            'pinterest_url' => 'https://pinterest.com/donaldsextonphoto',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('"sameAs":', false)
            ->assertSee('https://instagram.com/donaldsextonphoto', false)
            ->assertSee('https://pinterest.com/donaldsextonphoto', false);
    }

    public function test_organization_schema_includes_aggregate_rating_when_gbp_snapshot_available(): void
    {
        $this->mock(GoogleBusinessProfile::class, function ($mock): void {
            $mock->shouldReceive('snapshot')->andReturn([
                'rating' => 4.9,
                'reviewCount' => 42,
                'recentReviews' => [
                    [
                        'author' => 'Avery K.',
                        'rating' => 5,
                        'excerpt' => 'Calm, kind, and the photos are unreal.',
                        'date' => 'Mar 5, 2026',
                    ],
                ],
            ]);
            $mock->shouldReceive('publicListingUrl')->andReturn(null);
        });

        $this->get('/')
            ->assertOk()
            ->assertSee('"@type":"AggregateRating"', false)
            ->assertSee('"ratingValue":4.9', false)
            ->assertSee('"reviewCount":42', false)
            ->assertSee('"@type":"Review"', false)
            ->assertSee('Calm, kind, and the photos are unreal.', false);
    }

    public function test_homepage_renders_google_reviews_block_when_snapshot_available(): void
    {
        $this->mock(GoogleBusinessProfile::class, function ($mock): void {
            $mock->shouldReceive('snapshot')->andReturn([
                'rating' => 4.9,
                'reviewCount' => 42,
                'recentReviews' => [
                    [
                        'author' => 'Avery K.',
                        'rating' => 5,
                        'excerpt' => 'Calm, kind, and the photos are unreal.',
                        'date' => 'Mar 5, 2026',
                    ],
                ],
            ]);
            $mock->shouldReceive('publicListingUrl')->andReturn('https://www.google.com/search?q=test');
        });

        $this->get('/')
            ->assertOk()
            ->assertSee('google-reviews', false)
            ->assertSee('42 reviews on Google', false)
            ->assertSee('Calm, kind, and the photos are unreal.')
            ->assertSee('Avery K.');
    }

    public function test_inquiry_page_renders_google_reviews_aside_when_snapshot_available(): void
    {
        $this->mock(GoogleBusinessProfile::class, function ($mock): void {
            $mock->shouldReceive('snapshot')->andReturn([
                'rating' => 5.0,
                'reviewCount' => 12,
                'recentReviews' => [
                    [
                        'author' => 'Jamie R.',
                        'rating' => 5,
                        'excerpt' => 'Felt like a friend with a camera.',
                        'date' => 'Apr 2, 2026',
                    ],
                ],
            ]);
            $mock->shouldReceive('publicListingUrl')->andReturn(null);
        });

        $this->get(route('inquiry.create'))
            ->assertOk()
            ->assertSee('google-reviews--aside', false)
            ->assertSee('12 reviews on Google', false)
            ->assertSee('Felt like a friend with a camera.');
    }

    public function test_homepage_does_not_render_google_reviews_block_when_no_snapshot(): void
    {
        $this->mock(GoogleBusinessProfile::class, function ($mock): void {
            $mock->shouldReceive('snapshot')->andReturn(null);
            $mock->shouldReceive('publicListingUrl')->andReturn(null);
        });

        $this->get('/')
            ->assertOk()
            ->assertDontSee('google-reviews', false);
    }

    public function test_layout_renders_verification_meta_tags_when_codes_are_set(): void
    {
        SiteSetting::create([
            'google_site_verification' => 'abc-google-token',
            'bing_site_verification' => 'BING123',
            'pinterest_site_verification' => 'pinterest-claim-456',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="abc-google-token">', false)
            ->assertSee('<meta name="msvalidate.01" content="BING123">', false)
            ->assertSee('<meta name="p:domain_verify" content="pinterest-claim-456">', false);
    }
}

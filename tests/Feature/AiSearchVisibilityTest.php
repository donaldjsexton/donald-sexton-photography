<?php

namespace Tests\Feature;

use App\Http\Controllers\SitemapController;
use App\Models\JournalPost;
use App\Models\SiteSetting;
use App\Models\Venue;
use App\Models\WeddingStory;
use App\Support\FloridaGeo;
use App\Support\HomepageBlocksSeeder;
use App\Support\VenueLandingCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSearchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_florida_coordinates_are_west_and_north_in_json_ld(): void
    {
        SiteSetting::create([
            'business_latitude' => -27.95,
            'business_longitude' => 82.787,
            'business_locality' => 'Clearwater',
            'business_region' => 'FL',
            'business_country' => 'US',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $this->assertValidJsonLd($response->getContent());
        $response->assertSee('"latitude":27.95', false);
        $response->assertSee('"longitude":-82.787', false);
        $response->assertDontSee('"longitude":82.787', false);

        $this->assertSame(1, FloridaGeo::correctStoredSettings());

        $stored = SiteSetting::query()->first();
        $this->assertEqualsWithDelta(27.95, (float) $stored->business_latitude, 0.00001);
        $this->assertEqualsWithDelta(-82.787, (float) $stored->business_longitude, 0.00001);
        $this->assertSame(0, FloridaGeo::correctStoredSettings());
    }

    public function test_blank_address_still_corrects_florida_magnitude_coordinates(): void
    {
        SiteSetting::create([
            'business_latitude' => 27.95,
            'business_longitude' => 82.787,
            'business_street_address' => '1153 Lakeview Rd',
            'business_postal_code' => '33756',
            'business_locality' => null,
            'business_region' => null,
            'business_country' => null,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $this->assertValidJsonLd($response->getContent());
        $response
            ->assertSee('"addressLocality":"Clearwater"', false)
            ->assertSee('"addressRegion":"FL"', false)
            ->assertSee('"latitude":27.95', false)
            ->assertSee('"longitude":-82.787', false)
            ->assertDontSee('"longitude":82.787', false);

        $this->assertSame(1, FloridaGeo::correctStoredSettings());
        $this->assertEqualsWithDelta(-82.787, (float) SiteSetting::query()->first()->business_longitude, 0.00001);
    }

    public function test_dotted_florida_region_and_clearwater_locality_are_corrected(): void
    {
        SiteSetting::create([
            'business_latitude' => 27.95,
            'business_longitude' => 82.787,
            'business_locality' => 'Clearwater Beach',
            'business_region' => 'F.L.',
            'business_country' => 'US',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('"longitude":-82.787', false);
    }

    public function test_non_us_country_keeps_a_positive_eastern_longitude(): void
    {
        SiteSetting::create([
            'business_latitude' => 27.95,
            'business_longitude' => 82.787,
            'business_locality' => null,
            'business_region' => null,
            'business_country' => 'IN',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('"longitude":82.787', false)
            ->assertDontSee('"longitude":-82.787', false);
    }

    public function test_non_florida_positive_longitude_is_left_alone(): void
    {
        SiteSetting::create([
            'business_latitude' => 13.4443,
            'business_longitude' => 144.7937,
            'business_locality' => 'Hagatna',
            'business_region' => 'GU',
            'business_country' => 'US',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('"latitude":13.4443', false)
            ->assertSee('"longitude":144.7937', false);
    }

    public function test_homepage_has_about_and_service_area_copy(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Based in Clearwater.')
            ->assertSee('I am Donald Sexton, a wedding photographer based in Clearwater.')
            ->assertSee('Honeymoon Island in Dunedin')
            ->assertSee('Clearwater Beach')
            ->assertSee('Hilton Clearwater Beach')
            ->assertSee('Collections')
            ->assertSee('$2,100')
            ->assertSee(route('venues.show', VenueLandingCopy::HONEYMOON_SLUG), false)
            ->assertSee(route('venues.show', VenueLandingCopy::HILTON_SLUG), false)
            ->assertSee(route('pages.about'), false);

        HomepageBlocksSeeder::seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('Based in Clearwater.')
            ->assertSee('Honeymoon Island in Dunedin');
    }

    public function test_llms_txt_uses_on_site_urls_descriptive_titles_and_a_24_hour_reply(): void
    {
        WeddingStory::create([
            'title' => 'Alex + Alex - Wedding at Knotted Roots on the Lake',
            'slug' => 'alex-alex-wedding',
            'status' => 'published',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-rodriguezwedding',
            'published_at' => now()->subDay(),
        ]);

        WeddingStory::create([
            'title' => 'Mel + Adam - Wedding on Indian Rocks Beach',
            'slug' => 'mel-adam-wedding-on-indian-rocks-beach',
            'status' => 'published',
            'original_wp_url' => 'https://donaldsextonphotography.com/mel-adam',
            'published_at' => now()->subDays(40),
        ]);

        JournalPost::create([
            'title' => 'Clipp',
            'slug' => 'clipp',
            'status' => 'published',
            'post_type' => 'real_wedding',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-clipp',
            'original_wp_url' => 'https://donaldsextonphotography.com/mel-adam',
            'seo_description' => 'Learn what causes blown highlights and clipping in wedding photos.',
            'published_at' => now(),
        ]);

        $venue = Venue::factory()->create([
            'name' => 'Coquina Beach',
            'slug' => 'coquina-beach',
        ]);

        $bryant = JournalPost::create([
            'title' => 'Bryant',
            'slug' => 'bryant',
            'status' => 'published',
            'post_type' => 'real_wedding',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-bryant',
            'published_at' => now()->subHour(),
        ]);
        $bryant->venues()->attach($venue);

        $body = $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertStringContainsString('usually within 24 hours', $body);
        $this->assertStringNotContainsString('couple of business days', $body);
        $this->assertStringNotContainsString('pic-time.com', $body);
        $this->assertStringContainsString(route('weddings.show', 'alex-alex-wedding'), $body);
        $this->assertStringContainsString('Mel + Adam - Wedding on Indian Rocks Beach', $body);
        $this->assertStringContainsString(route('weddings.show', 'mel-adam-wedding-on-indian-rocks-beach'), $body);
        $this->assertStringNotContainsString('- [Clipp]', $body);
        $this->assertStringContainsString('Bryant — Real Wedding at Coquina Beach', $body);
        $this->assertStringContainsString(route('journal.show', 'bryant'), $body);
        $this->assertStringNotContainsString('blown highlights', $body);
        $this->assertStringContainsString(route('venues.show', VenueLandingCopy::HONEYMOON_SLUG), $body);
        $this->assertStringContainsString(route('venues.show', VenueLandingCopy::HILTON_SLUG), $body);
    }

    public function test_archived_slug_duplicate_redirects_to_the_canonical_story_and_leaves_the_sitemap(): void
    {
        WeddingStory::create([
            'title' => 'Dalka Engagement',
            'slug' => 'dalka-engagement',
            'status' => 'published',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-dalkaengagement',
            'published_at' => now(),
        ]);

        JournalPost::create([
            'title' => 'Kasey + Amber - Engagement at Sand Key Beach Clearwater',
            'slug' => 'kasey-amber-engagement-at-sand-key-beach-clearwater',
            'status' => 'published',
            'post_type' => 'engagement',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-dalkaengagement',
            'published_at' => now(),
        ]);

        JournalPost::create([
            'title' => 'Kasey + Amber - Engagement at Sand Key Beach Clearwater',
            'slug' => 'kasey-amber-engagement-at-sand-key-beach-clearwater-archived-233',
            'status' => 'published',
            'post_type' => 'engagement',
            'published_at' => now(),
        ]);

        $this->get('/journal/kasey-amber-engagement-at-sand-key-beach-clearwater-archived-233')
            ->assertRedirect(route('weddings.show', 'dalka-engagement'));

        $this->get('/journal')
            ->assertOk()
            ->assertDontSee('archived-233', false);

        SitemapController::forgetCache();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('archived-233', false)
            ->assertSee(route('venues.show', VenueLandingCopy::HILTON_SLUG), false);
    }

    public function test_archived_slug_without_a_canonical_target_is_noindex_and_omitted_from_the_sitemap(): void
    {
        JournalPost::create([
            'title' => 'Old Duplicate',
            'slug' => 'old-duplicate-archived-233',
            'status' => 'published',
            'post_type' => 'advice',
            'excerpt' => 'A leftover duplicate with no canonical target.',
            'body' => '<p>A leftover duplicate with no canonical target.</p>',
            'published_at' => now(),
        ]);

        $this->get('/journal/old-duplicate-archived-233')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('A leftover duplicate with no canonical target.');

        SitemapController::forgetCache();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('old-duplicate-archived-233', false);
    }

    public function test_honeymoon_island_page_adds_plain_text_and_faq_without_overwriting_saved_copy(): void
    {
        $venue = Venue::factory()->create([
            'name' => 'Romantic Honeymoon Island Weddings',
            'slug' => VenueLandingCopy::HONEYMOON_SLUG,
            'summary' => null,
            'body' => null,
            'faqs' => null,
        ]);

        JournalPost::create([
            'title' => "Lauren & Jonathan's Engagement on Honeymoon Island",
            'slug' => VenueLandingCopy::LAUREN_SLUG,
            'status' => 'published',
            'post_type' => 'engagement',
            'excerpt' => 'A few intimate photos from their engagement session on Honeymoon Island.',
            'published_at' => now(),
        ]);

        $this->get(route('venues.show', VenueLandingCopy::HONEYMOON_SLUG))
            ->assertOk()
            ->assertSee('Do you photograph elopements on Honeymoon Island?')
            ->assertSee('What does a Honeymoon Island elopement start at?')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('Dunedin')
            ->assertSee('$2,100')
            ->assertSee(route('journal.show', VenueLandingCopy::LAUREN_SLUG), false)
            ->assertSee('within 24 hours');

        $this->assertNull($venue->fresh()->body);
        $this->assertNull($venue->fresh()->faqs);
    }

    public function test_hilton_clearwater_beach_page_links_the_krysinsky_wedding(): void
    {
        WeddingStory::create([
            'title' => 'Krysinsky Wedding',
            'slug' => VenueLandingCopy::KRYSINSKY_SLUG,
            'status' => 'published',
            'location_name' => 'Hilton Clearwater Beach Resort & Spa',
            'published_at' => now(),
        ]);

        JournalPost::create([
            'title' => 'John + Sherie - Wedding Wyndham Grand Clearwater Beach',
            'slug' => 'john-sherie-wedding',
            'status' => 'published',
            'post_type' => 'real_wedding',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-krysinski',
            'published_at' => now(),
        ]);

        $this->get(route('weddings.show', VenueLandingCopy::KRYSINSKY_SLUG))
            ->assertOk()
            ->assertSee('This day was photographed at Hilton Clearwater Beach Resort')
            ->assertSee(route('venues.show', VenueLandingCopy::HILTON_SLUG), false);

        $this->get(route('venues.show', VenueLandingCopy::HILTON_SLUG))
            ->assertOk()
            ->assertSee('Have you photographed a wedding at Hilton Clearwater Beach?')
            ->assertSee('Krysinsky Wedding')
            ->assertSee(route('weddings.show', VenueLandingCopy::KRYSINSKY_SLUG), false)
            ->assertSee(route('journal.show', 'john-sherie-wedding'), false)
            ->assertSee('a journal note', false)
            ->assertSee('$2,100');

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertSee(route('venues.show', VenueLandingCopy::HONEYMOON_SLUG), false)
            ->assertSee(route('venues.show', VenueLandingCopy::HILTON_SLUG), false);
    }

    private function assertValidJsonLd(string $html): void
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $json) {
            json_decode($json, true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), $json);
        }
    }
}

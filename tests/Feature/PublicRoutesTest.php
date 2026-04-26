<?php

namespace Tests\Feature;

use App\Mail\InquiryReceived;
use App\Models\Category;
use App\Models\Collection;
use App\Models\HomepageSetting;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_content_routes_resolve(): void
    {
        $venue = Venue::create([
            'name' => 'Knotted Roots on the Lake',
            'slug' => 'knotted-roots-on-the-lake',
            'summary' => 'Venue summary.',
        ]);

        Page::create([
            'title' => 'About',
            'slug' => 'about',
            'template' => 'about',
            'status' => 'published',
            'body' => '<p>About body.</p>',
            'published_at' => now(),
        ]);

        Page::create([
            'title' => 'Clearwater Wedding Photographer',
            'slug' => 'clearwater-wedding-photographer',
            'template' => 'location',
            'status' => 'published',
            'body' => '<p>Location body.</p>',
            'published_at' => now(),
        ]);

        Page::create([
            'title' => 'Collections',
            'slug' => 'collections',
            'template' => 'collections',
            'status' => 'published',
            'body' => '<p>Collections body.</p>',
            'published_at' => now(),
        ]);

        Collection::create([
            'name' => 'Core Coverage',
            'slug' => 'core-coverage',
            'status' => 'published',
            'summary' => 'Collection summary.',
            'starting_price' => 5500,
            'display_order' => 1,
        ]);

        $story = WeddingStory::create([
            'title' => 'Classic Clearwater Wedding',
            'slug' => 'classic-clearwater-wedding',
            'status' => 'published',
            'excerpt' => 'Story excerpt.',
            'venue_id' => $venue->id,
            'published_at' => now(),
        ]);

        $category = Category::create([
            'name' => 'Wedding Advice',
            'slug' => 'wedding-advice',
        ]);

        $tag = Tag::create([
            'name' => 'Clearwater',
            'slug' => 'clearwater',
        ]);

        $post = JournalPost::create([
            'title' => 'How to Build a Wedding Timeline',
            'slug' => 'wedding-timeline-guide',
            'status' => 'published',
            'post_type' => 'advice',
            'excerpt' => 'Post excerpt.',
            'body' => '<p>Post body.</p>',
            'published_at' => now(),
        ]);

        $post->categories()->attach($category);
        $post->tags()->attach($tag);
        $post->venues()->attach($venue);

        Testimonial::create([
            'quote' => 'Excellent work.',
            'author_name' => 'Test Couple',
            'is_featured' => true,
        ]);

        HomepageSetting::create([
            'hero_heading' => 'Hero heading',
            'featured_story_ids_json' => [$story->id],
            'featured_journal_post_ids_json' => [$post->id],
        ]);

        $this->get('/')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/collections')->assertOk();
        $this->get('/weddings')->assertOk();
        $this->get('/weddings/classic-clearwater-wedding')->assertOk();
        $this->get('/journal')->assertOk();
        $this->get('/journal/category/wedding-advice')->assertOk();
        $this->get('/journal/tag/clearwater')->assertOk();
        $this->get('/journal/wedding-timeline-guide')->assertOk();
        $this->get('/venues')->assertOk();
        $this->get('/venues/knotted-roots-on-the-lake')->assertOk();
        $this->get('/locations/clearwater-wedding-photographer')->assertOk();
        $this->get('/inquire')->assertOk();
        $this->get('/thank-you')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_about_route_falls_back_when_about_page_is_missing(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('I photograph weddings with a calm, simple approach', false);
    }

    public function test_homepage_navigation_hides_about_link_for_now(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('href="/about"', false)
            ->assertSee('site-nav__toggle', false)
            ->assertSee('Check Availability', false);
    }

    public function test_collections_route_falls_back_to_clear_guidance_when_no_collections_exist(): void
    {
        $this->get('/collections')
            ->assertOk()
            ->assertSee('Essential', false)
            ->assertSee('Complete', false)
            ->assertSee('Extended', false)
            ->assertSee('Add-ons', false);
    }

    public function test_homepage_renders_default_seo_metadata_and_structured_data(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="description" content="Calm wedding photography for Clearwater, Tampa, and beyond. Real wedding stories, planning guidance, and straightforward next steps.">', false)
            ->assertSee('<meta name="robots" content="index,follow,max-image-preview:large">', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"@type":"WeddingPhotographer"', false)
            ->assertSee('"addressLocality":"Clearwater"', false);
    }

    public function test_public_layout_renders_google_analytics_when_platform_setting_exists(): void
    {
        SiteSetting::create([
            'google_analytics_measurement_id' => 'G-TEST12345',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST12345', false)
            ->assertSee("gtag('config', 'G-TEST12345');", false);
    }

    public function test_homepage_uses_sequential_unique_story_pool_for_hero_and_discover(): void
    {
        $stories = collect(range(1, 5))->map(function (int $index) {
            $media = Media::create([
                'disk' => 'public',
                'path' => 'media/test/story-'.$index.'.jpg',
                'filename' => 'story-'.$index.'.jpg',
                'mime_type' => 'image/jpeg',
                'width' => 1200,
                'height' => 1600,
            ]);

            return WeddingStory::create([
                'title' => 'Story '.$index,
                'slug' => 'story-'.$index,
                'status' => 'published',
                'excerpt' => 'Excerpt '.$index,
                'published_at' => now()->subDays($index),
                'display_order' => $index,
                'is_featured' => $index === 1,
                'hero_media_id' => $media->id,
            ]);
        });

        HomepageSetting::create([
            'featured_story_ids_json' => [$stories->first()->id],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('home-hero__triptych', false);
        $response->assertSee('home-hero__solo', false);
        $response->assertSee('alt="Story 1"', false);
        $response->assertSee('alt="Story 2"', false);
        $response->assertSee('alt="Story 3"', false);
        $response->assertSee('alt="Story 4"', false);
        $response->assertSee('alt="Story 5"', false);
    }

    public function test_wedding_archive_is_sorted_by_publish_date_desc(): void
    {
        WeddingStory::create([
            'title' => 'Older Featured Story',
            'slug' => 'older-featured-story',
            'status' => 'published',
            'is_featured' => true,
            'display_order' => 1,
            'published_at' => now()->subDays(5),
        ]);

        WeddingStory::create([
            'title' => 'Newest Story',
            'slug' => 'newest-story',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/weddings')
            ->assertOk()
            ->assertSeeInOrder(['Newest Story', 'Older Featured Story'], false);
    }

    public function test_journal_archive_is_sorted_by_publish_date_desc(): void
    {
        JournalPost::create([
            'title' => 'Older Journal Post',
            'slug' => 'older-journal-post',
            'status' => 'published',
            'post_type' => 'advice',
            'published_at' => now()->subDays(6),
        ]);

        JournalPost::create([
            'title' => 'Newest Journal Post',
            'slug' => 'newest-journal-post',
            'status' => 'published',
            'post_type' => 'advice',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/journal')
            ->assertOk()
            ->assertSeeInOrder(['Newest Journal Post', 'Older Journal Post'], false);
    }

    public function test_homepage_does_not_emit_legacy_wordpress_upload_images(): void
    {
        $story = WeddingStory::create([
            'title' => 'Legacy Imported Story',
            'slug' => 'legacy-imported-story',
            'status' => 'published',
            'body' => <<<'HTML'
<p>Lead paragraph.</p>
<figure class="wp-import-gallery">
    <figure class="wp-import-image"><img src="https://donaldsextonphotography.com/wp-content/uploads/2025/11/legacy-image-scaled.jpg" alt=""></figure>
</figure>
HTML,
            'original_wp_post_id' => 40433,
            'published_at' => now(),
        ]);

        HomepageSetting::create([
            'featured_story_ids_json' => [$story->id],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('wp-content/uploads', false);
    }

    public function test_inquiry_submission_persists_and_redirects(): void
    {
        $response = $this->post('/inquire', [
            'primary_name' => 'Test Couple',
            'email' => 'test@example.com',
            'event_type' => 'wedding',
            'message' => 'We want to book.',
            'coverage_interest' => ['Wedding day coverage'],
            'utm_source' => 'instagram',
            'utm_medium' => 'social',
            'utm_campaign' => 'spring-launch',
        ]);

        $response->assertRedirect(route('inquiry.thank-you'));

        $this->assertDatabaseHas('inquiries', [
            'primary_name' => 'Test Couple',
            'email' => 'test@example.com',
            'source' => 'site_form',
            'utm_source' => 'instagram',
            'utm_medium' => 'social',
            'utm_campaign' => 'spring-launch',
        ]);
    }

    public function test_inquiry_submission_sends_notification_when_recipient_is_configured(): void
    {
        Mail::fake();

        config()->set('mail.inquiry_to', 'studio@example.com');

        $this->post('/inquire', [
            'primary_name' => 'Taylor',
            'partner_name' => 'Jordan',
            'email' => 'taylor@example.com',
            'event_type' => 'wedding',
            'event_date' => '2027-05-15',
            'venue_name' => 'Sand Key Beach',
            'guest_count_range' => '100-150',
            'budget_range' => '$6,000-$8,000',
            'message' => 'We would love to learn more.',
        ])->assertRedirect(route('inquiry.thank-you'));

        Mail::assertSent(InquiryReceived::class, function (InquiryReceived $mail): bool {
            return $mail->hasTo('studio@example.com')
                && $mail->inquiry->primary_name === 'Taylor'
                && $mail->inquiry->email === 'taylor@example.com';
        });
    }

    public function test_inquiry_form_preserves_utm_query_parameters(): void
    {
        $this->get('/inquire?utm_source=google&utm_medium=cpc&utm_campaign=summer')
            ->assertOk()
            ->assertSee('name="utm_source" value="google"', false)
            ->assertSee('name="utm_medium" value="cpc"', false)
            ->assertSee('name="utm_campaign" value="summer"', false);
    }

    public function test_page_routes_render_seo_metadata(): void
    {
        Page::create([
            'title' => 'About',
            'slug' => 'about',
            'template' => 'about',
            'status' => 'published',
            'excerpt' => 'Short about page summary.',
            'body' => '<p>About body.</p>',
            'seo_title' => 'About Donald Sexton Photography',
            'seo_description' => 'Editorial wedding photography in Clearwater and Tampa.',
            'canonical_url' => 'https://donaldsexton.com/about',
            'published_at' => now(),
        ]);

        $this->get('/about')
            ->assertOk()
            ->assertSee('<title>About Donald Sexton Photography</title>', false)
            ->assertSee('<meta name="description" content="Editorial wedding photography in Clearwater and Tampa.">', false)
            ->assertSee('<link rel="canonical" href="https://donaldsexton.com/about">', false)
            ->assertSee('<meta property="og:title" content="About Donald Sexton Photography">', false)
            ->assertSee('<meta property="og:description" content="Editorial wedding photography in Clearwater and Tampa.">', false)
            ->assertSee('<meta property="og:url" content="https://donaldsexton.com/about">', false)
            ->assertSee('<meta name="twitter:title" content="About Donald Sexton Photography">', false);
    }

    public function test_journal_routes_render_human_post_type_labels(): void
    {
        $advicePost = JournalPost::create([
            'title' => 'Advice Entry',
            'slug' => 'advice-entry',
            'status' => 'published',
            'post_type' => 'advice',
            'body' => '<p>Advice body.</p>',
            'published_at' => now(),
        ]);

        $post = JournalPost::create([
            'title' => 'Real Wedding Story',
            'slug' => 'real-wedding-story',
            'status' => 'archived',
            'post_type' => 'real_wedding',
            'body' => '<p>Post body.</p>',
            'published_at' => now(),
        ]);

        WeddingStory::create([
            'title' => 'Real Wedding Story',
            'slug' => 'real-wedding-story',
            'status' => 'published',
            'story_type' => 'wedding',
            'published_at' => now(),
        ]);

        Redirect::create([
            'from_path' => '/journal/real-wedding-story',
            'to_path' => '/weddings/real-wedding-story',
            'status_code' => 301,
            'source' => 'wp_import',
        ]);

        $this->get('/journal')
            ->assertOk()
            ->assertSee($advicePost->title)
            ->assertDontSee('Real_wedding');

        $this->get('/journal/'.$post->slug)
            ->assertRedirect('/weddings/real-wedding-story');
    }

    public function test_venue_index_counts_only_published_related_content(): void
    {
        $venue = Venue::create([
            'name' => 'Knotted Roots on the Lake',
            'slug' => 'knotted-roots-on-the-lake',
            'summary' => 'Venue summary.',
        ]);

        WeddingStory::create([
            'title' => 'Published Wedding',
            'slug' => 'published-wedding',
            'status' => 'published',
            'venue_id' => $venue->id,
            'published_at' => now()->subDay(),
        ]);

        WeddingStory::create([
            'title' => 'Future Wedding',
            'slug' => 'future-wedding',
            'status' => 'published',
            'venue_id' => $venue->id,
            'published_at' => now()->addDay(),
        ]);

        $publishedPost = JournalPost::create([
            'title' => 'Published Venue Guide',
            'slug' => 'published-venue-guide',
            'status' => 'published',
            'post_type' => 'venue',
            'published_at' => now()->subDay(),
        ]);

        $draftPost = JournalPost::create([
            'title' => 'Draft Venue Guide',
            'slug' => 'draft-venue-guide',
            'status' => 'draft',
            'post_type' => 'venue',
        ]);

        $venue->journalPosts()->attach([$publishedPost->id, $draftPost->id]);

        $response = $this->get('/venues');

        $response->assertOk();

        $listedVenue = $response->viewData('venues')
            ->getCollection()
            ->firstWhere('id', $venue->id);

        $this->assertNotNull($listedVenue);
        $this->assertSame(1, $listedVenue->wedding_stories_count);
        $this->assertSame(1, $listedVenue->journal_posts_count);
    }

    public function test_fallback_redirects_legacy_paths(): void
    {
        Redirect::create([
            'from_path' => '/old-post/',
            'to_path' => '/journal/new-post',
            'status_code' => 301,
        ]);

        $this->get('/old-post')->assertRedirect('/journal/new-post');
    }

    public function test_wedding_routes_honor_stored_redirects_when_story_slug_is_missing(): void
    {
        Redirect::create([
            'from_path' => '/weddings/legacy-story',
            'to_path' => '/journal/current-post',
            'status_code' => 301,
            'source' => 'wp_import',
        ]);

        $this->get('/weddings/legacy-story')->assertRedirect('/journal/current-post');
    }

    public function test_public_pages_render_cleanly_without_media_placeholders(): void
    {
        $story = WeddingStory::create([
            'title' => 'Minimal Story',
            'slug' => 'minimal-story',
            'status' => 'published',
            'excerpt' => 'A short story summary.',
            'published_at' => now(),
        ]);

        $post = JournalPost::create([
            'title' => 'Minimal Journal Entry',
            'slug' => 'minimal-journal-entry',
            'status' => 'published',
            'post_type' => 'journal',
            'excerpt' => 'A short journal summary.',
            'body' => '<p>Journal body.</p>',
            'published_at' => now(),
        ]);

        HomepageSetting::create([
            'featured_story_ids_json' => [$story->id],
            'featured_journal_post_ids_json' => [$post->id],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee($story->title)
            ->assertDontSee('Editorial Frame')
            ->assertDontSee('View Story');

        $this->get('/weddings')
            ->assertOk()
            ->assertSee($story->title)
            ->assertDontSee('Editorial Frame')
            ->assertDontSee('View Story');

        $this->get('/weddings/minimal-story')
            ->assertOk()
            ->assertDontSee('Story Detail')
            ->assertDontSee('Wedding Day');
    }

    public function test_homepage_backfills_missing_selected_content_with_published_items(): void
    {
        $story = WeddingStory::create([
            'title' => 'Fallback Story',
            'slug' => 'fallback-story',
            'status' => 'published',
            'excerpt' => 'Fallback story excerpt.',
            'published_at' => now()->subDay(),
        ]);

        $post = JournalPost::create([
            'title' => 'Fallback Journal Entry',
            'slug' => 'fallback-journal-entry',
            'status' => 'published',
            'post_type' => 'journal',
            'excerpt' => 'Fallback journal excerpt.',
            'body' => '<p>Fallback journal body.</p>',
            'published_at' => now()->subHours(6),
        ]);

        HomepageSetting::create([
            'featured_story_ids_json' => [9999],
            'featured_journal_post_ids_json' => [9999],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee($story->title)
            ->assertSee($post->title);
    }

    public function test_blank_journal_post_renders_graceful_empty_content_fallback(): void
    {
        $blankPost = JournalPost::create([
            'title' => 'Blank Legacy Post',
            'slug' => 'blank-legacy-post',
            'status' => 'published',
            'post_type' => 'journal',
            'published_at' => now(),
        ]);

        $response = $this->get('/journal/'.$blankPost->slug)
            ->assertOk()
            ->assertSee($blankPost->title)
            ->assertSee('The full write-up for this post is on its way.');

        $populatedPost = JournalPost::create([
            'title' => 'Populated Journal Post',
            'slug' => 'populated-journal-post',
            'status' => 'published',
            'post_type' => 'journal',
            'body' => '<p>This post has real content that should render.</p>',
            'published_at' => now(),
        ]);

        $this->get('/journal/'.$populatedPost->slug)
            ->assertOk()
            ->assertSee('This post has real content that should render.')
            ->assertDontSee('The full write-up for this post is on its way.');
    }

    public function test_journal_post_renders_blog_posting_json_ld_schema(): void
    {
        $post = JournalPost::create([
            'title' => 'Schema Test Post',
            'slug' => 'schema-test-post',
            'status' => 'published',
            'post_type' => 'journal',
            'excerpt' => 'A short excerpt for the schema description.',
            'author_name' => 'Donald Sexton',
            'body' => '<p>Real content.</p>',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/journal/'.$post->slug)
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"headline":"Schema Test Post"', false)
            ->assertSee('"description":"A short excerpt for the schema description."', false)
            ->assertSee('"author":{"@type":"Person","name":"Donald Sexton"}', false)
            ->assertSee('"mainEntityOfPage":{"@type":"WebPage"', false);
    }

    public function test_archive_pages_render_intentional_empty_states(): void
    {
        $this->get('/weddings')
            ->assertOk()
            ->assertSee('More wedding stories are on the way.')
            ->assertDontSee('No wedding stories are published yet.');

        $this->get('/journal')
            ->assertOk()
            ->assertSee('New posts are on the way.')
            ->assertDontSee('No journal posts are published yet.');

        $this->get('/collections')
            ->assertOk()
            ->assertSee('Essential')
            ->assertDontSee('No collections are published yet.');

        $this->get('/venues')
            ->assertOk()
            ->assertSee('Your venue does not need to be listed here yet.')
            ->assertDontSee('No venues have been added yet.');
    }

    public function test_venue_page_without_related_content_collapses_empty_sections(): void
    {
        $venue = Venue::create([
            'name' => 'Quiet Venue',
            'slug' => 'quiet-venue',
            'summary' => 'Venue summary.',
        ]);

        $this->get('/venues/'.$venue->slug)
            ->assertOk()
            ->assertSee('You can still start with this venue.')
            ->assertDontSee('No published wedding stories are linked to this venue yet.')
            ->assertDontSee('No journal posts are linked to this venue yet.');
    }

    public function test_inquiry_form_shows_venue_autocomplete(): void
    {
        $this->get('/inquire')
            ->assertOk()
            ->assertSee('data-venue-autocomplete', false)
            ->assertSee('name="venue_name"', false)
            ->assertSee('name="venue_id"', false);
    }

    public function test_inquiry_form_omits_deferred_qualification_fields(): void
    {
        $response = $this->get('/inquire')->assertOk();

        foreach (['partner_name', 'location_city', 'guest_count_range', 'budget_range', 'instagram_handle'] as $field) {
            $response->assertDontSee('name="'.$field.'"', false);
        }
    }

    public function test_sitemap_lists_key_public_routes_with_lastmod_metadata(): void
    {
        Page::create([
            'title' => 'About',
            'slug' => 'about',
            'template' => 'about',
            'status' => 'published',
            'body' => '<p>About body.</p>',
            'published_at' => now()->subDay(),
        ]);

        Page::create([
            'title' => 'Collections',
            'slug' => 'collections',
            'template' => 'collections',
            'status' => 'published',
            'body' => '<p>Collections body.</p>',
            'published_at' => now()->subDay(),
        ]);

        $story = WeddingStory::create([
            'title' => 'Sitemap Story',
            'slug' => 'sitemap-story',
            'status' => 'published',
            'published_at' => now()->subHours(3),
        ]);

        $post = JournalPost::create([
            'title' => 'Sitemap Post',
            'slug' => 'sitemap-post',
            'status' => 'published',
            'post_type' => 'journal',
            'body' => '<p>Journal body.</p>',
            'published_at' => now()->subHours(2),
        ]);

        Venue::create([
            'name' => 'Sitemap Venue',
            'slug' => 'sitemap-venue',
            'summary' => 'Venue summary.',
        ]);

        HomepageSetting::create([
            'hero_heading' => 'Homepage heading',
            'featured_story_ids_json' => [$story->id],
            'featured_journal_post_ids_json' => [$post->id],
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.route('home').'</loc>', false)
            ->assertSee('<loc>'.route('weddings.show', $story->slug).'</loc>', false)
            ->assertSee('<loc>'.route('journal.show', $post->slug).'</loc>', false)
            ->assertSee('<loc>'.route('collections.index').'</loc>', false)
            ->assertDontSee('<loc>'.route('pages.about').'</loc>', false)
            ->assertSee('<lastmod>', false);
    }

    public function test_robots_txt_advertises_sitemap_and_disallows_admin(): void
    {
        $response = $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $body = $response->getContent();

        $this->assertStringContainsString('User-agent: *', $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $body);
    }

    public function test_journal_post_renders_og_image_and_article_metadata(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/journal-hero.jpg',
            'filename' => 'journal-hero.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1600,
            'height' => 900,
        ]);

        $post = JournalPost::create([
            'title' => 'OG Image Journal Post',
            'slug' => 'og-image-journal-post',
            'status' => 'published',
            'post_type' => 'journal',
            'author_name' => 'Donald Sexton',
            'body' => '<p>Body.</p>',
            'hero_media_id' => $hero->id,
            'published_at' => now()->subDay(),
        ]);

        $absoluteImage = url($hero->publicUrl());

        $this->get('/journal/'.$post->slug)
            ->assertOk()
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta property="og:image" content="'.$absoluteImage.'">', false)
            ->assertSee('<meta property="og:image:alt" content="OG Image Journal Post">', false)
            ->assertSee('<meta name="twitter:image" content="'.$absoluteImage.'">', false)
            ->assertSee('<meta property="article:published_time" content="'.$post->published_at->toIso8601String().'">', false)
            ->assertSee('<meta property="article:author" content="Donald Sexton">', false);
    }

    public function test_wedding_story_renders_og_image_with_absolute_url(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/wedding-hero.jpg',
            'filename' => 'wedding-hero.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1600,
            'height' => 900,
        ]);

        $story = WeddingStory::create([
            'title' => 'OG Image Wedding Story',
            'slug' => 'og-image-wedding-story',
            'status' => 'published',
            'hero_media_id' => $hero->id,
            'published_at' => now()->subDay(),
        ]);

        $absoluteImage = url($hero->publicUrl());

        $this->get('/weddings/'.$story->slug)
            ->assertOk()
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta property="og:image" content="'.$absoluteImage.'">', false)
            ->assertSee('<meta name="twitter:image" content="'.$absoluteImage.'">', false);
    }

    public function test_pages_without_a_featured_image_omit_og_image_when_no_default_configured(): void
    {
        config()->set('seo.default_og_image', '');

        Page::create([
            'title' => 'About',
            'slug' => 'about',
            'template' => 'about',
            'status' => 'published',
            'body' => '<p>About body.</p>',
            'published_at' => now(),
        ]);

        $this->get('/about')
            ->assertOk()
            ->assertDontSee('property="og:image"', false)
            ->assertDontSee('name="twitter:image"', false);
    }

    public function test_pages_use_configured_default_og_image_fallback(): void
    {
        config()->set('seo.default_og_image', '/storage/media/site/default-og.jpg');

        Page::create([
            'title' => 'About',
            'slug' => 'about',
            'template' => 'about',
            'status' => 'published',
            'body' => '<p>About body.</p>',
            'published_at' => now(),
        ]);

        $this->get('/about')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.url('/storage/media/site/default-og.jpg').'">', false);
    }

    public function test_imported_wedding_story_renders_lead_once_and_splits_gallery_from_body(): void
    {
        $story = WeddingStory::create([
            'title' => 'Imported Wedding Story',
            'slug' => 'imported-wedding-story',
            'status' => 'published',
            'excerpt' => 'Some weddings are beautiful, and some weddings are alive. Christopher and Jennifer’s day felt like a celebration...',
            'body' => <<<'HTML'
<p>Some weddings are beautiful, and some weddings are <em>alive.</em><br>Christopher and Jennifer’s day felt like a celebration from the moment it began.</p>
<figure class="wp-import-gallery">
    <figure class="wp-import-image"><img src="https://example.test/one.jpg" alt=""></figure>
    <figure class="wp-import-image"><img src="https://example.test/two.jpg" alt=""></figure>
</figure>
<p>This is the closing paragraph that should remain in the reading section.</p>
HTML,
            'original_wp_post_id' => 909,
            'published_at' => now(),
        ]);

        $response = $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('This is the closing paragraph that should remain in the reading section.', false)
            ->assertSee('loading="lazy"', false)
            ->assertSee('wp-import-gallery', false)
            ->assertDontSee('Some weddings are beautiful, and some weddings are alive. Christopher and Jennifer’s day felt like a celebration...', false);
    }

    public function test_homepage_uses_imported_story_image_when_local_hero_media_is_missing(): void
    {
        Page::create([
            'title' => 'About',
            'slug' => 'about',
            'template' => 'about',
            'status' => 'published',
            'body' => '<p>About body.</p>',
            'published_at' => now(),
        ]);

        Page::create([
            'title' => 'Collections',
            'slug' => 'collections',
            'template' => 'collections',
            'status' => 'published',
            'body' => '<p>Collections body.</p>',
            'published_at' => now(),
        ]);

        $story = WeddingStory::create([
            'title' => 'Imported Homepage Story',
            'slug' => 'imported-homepage-story',
            'status' => 'published',
            'excerpt' => 'Imported homepage excerpt.',
            'body' => '<p>Lead paragraph.</p><figure class="wp-import-gallery"><figure class="wp-import-image"><img src="https://example.test/homepage-featured.jpg" alt=""></figure></figure>',
            'original_wp_post_id' => 321,
            'published_at' => now(),
        ]);

        HomepageSetting::create([
            'hero_heading' => 'Homepage heading',
            'featured_story_ids_json' => [$story->id],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('https://example.test/homepage-featured.jpg', false);
    }

    public function test_pictime_wedding_story_renders_external_gallery_fallback_when_digest_is_thin(): void
    {
        $story = WeddingStory::create([
            'title' => 'Pic-Time Wedding Story',
            'slug' => 'pic-time-wedding-story',
            'status' => 'published',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-sample-wedding',
            'published_at' => now(),
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('Open Full Gallery')
            ->assertSee('https://donaldsextonphotography.pic-time.com/-sample-wedding', false)
            ->assertSee('If you want to see the full set of photos, you can open the full gallery in a new tab.');
    }

    public function test_pictime_journal_post_renders_external_gallery_fallback_when_digest_is_thin(): void
    {
        $post = JournalPost::create([
            'title' => 'Pic-Time Journal Post',
            'slug' => 'pic-time-journal-post',
            'status' => 'published',
            'post_type' => 'engagement',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-sample-journal',
            'published_at' => now(),
        ]);

        $this->get(route('journal.show', $post->slug))
            ->assertOk()
            ->assertSee('Open Full Gallery')
            ->assertSee('https://donaldsextonphotography.pic-time.com/-sample-journal', false)
            ->assertSee('If you want to see the full set of photos, you can open the full gallery in a new tab.');
    }

    public function test_legacy_pictime_embed_body_is_not_rendered_to_the_browser(): void
    {
        $story = WeddingStory::create([
            'title' => 'Legacy Pic-Time Story',
            'slug' => 'legacy-pictime-story',
            'status' => 'published',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-legacy-story',
            'body' => "<template data-pt-type='blog'></template><script src='https://gallery.donaldsextonphotography.com/-legacy/slideswebcomponentembed.js/abc123?features=lightbox,pinterest&filtertags=galleryaccess'></script>",
            'published_at' => now(),
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertDontSee('https://gallery.donaldsextonphotography.com/-legacy/slideswebcomponentembed.js/abc123?features=lightbox,pinterest&filtertags=galleryaccess', false)
            ->assertSee("data-pt-type='blog'", false)
            ->assertSee('https://donaldsextonphotography.pic-time.com/-legacy/slideswebcomponentembed.js/abc123?features=lightbox,pinterest&filtertags=galleryaccess', false)
            ->assertSee('Open Full Gallery');
    }

    public function test_pictime_wedding_story_can_render_embed_from_archived_journal_source(): void
    {
        JournalPost::create([
            'title' => 'Archived Pic-Time Source',
            'slug' => 'archived-pictime-source',
            'status' => 'archived',
            'post_type' => 'real_wedding',
            'original_wp_post_id' => 999,
            'body' => "<template data-pt-type='blog'></template><script src='https://gallery.donaldsextonphotography.com/-fallback/slideswebcomponentembed.js/xyz789?features=lightbox,pinterest&filtertags=galleryaccess'></script>",
        ]);

        $story = WeddingStory::create([
            'title' => 'Archived Pic-Time Source',
            'slug' => 'archived-pictime-source',
            'status' => 'published',
            'original_wp_post_id' => 999,
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-fallback',
            'published_at' => now(),
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('https://donaldsextonphotography.pic-time.com/-fallback/slideswebcomponentembed.js/xyz789?features=lightbox,pinterest&filtertags=galleryaccess', false)
            ->assertSee('Open Full Gallery');
    }

    public function test_pictime_wedding_story_renders_searchread_narrative_when_present(): void
    {
        $story = WeddingStory::create([
            'title' => 'Searchread Pic-Time Story',
            'slug' => 'searchread-pictime-story',
            'status' => 'published',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-searchread',
            'body' => <<<'HTML'
<script> const searchread_663a9e74d3e0db1ed870a445 = `Engagement
Searchread Pic-Time Story
November 21, 2022

Imagine stepping into a world where every moment is frozen in time.

Location: Sawgrass Lake Park

View Full Gallery`;</script><template data-pt-type='blog'></template><script src='https://gallery.donaldsextonphotography.com/-searchread/slideswebcomponentembed.js/abc123?features=lightbox,pinterest&filtertags=galleryaccess'></script>
HTML,
            'published_at' => now(),
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('Imagine stepping into a world where every moment is frozen in time.')
            ->assertSee('Location: Sawgrass Lake Park')
            ->assertSee('https://donaldsextonphotography.pic-time.com/-searchread/slideswebcomponentembed.js/abc123?features=lightbox,pinterest&filtertags=galleryaccess', false);
    }

    public function test_pictime_journal_post_renders_searchread_narrative_when_present(): void
    {
        $post = JournalPost::create([
            'title' => 'Searchread Pic-Time Journal',
            'slug' => 'searchread-pictime-journal',
            'status' => 'published',
            'post_type' => 'engagement',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-searchread-journal',
            'source_markup' => <<<'HTML'
<script> const searchread_663a9e74d3e0db1ed870a445 = `Engagement
Searchread Pic-Time Journal
November 21, 2022

Imagine stepping into a world where every moment is frozen in time.

Location: Sawgrass Lake Park

View Full Gallery`;</script><template data-pt-type='blog'></template><script src='https://gallery.donaldsextonphotography.com/-searchread-journal/slideswebcomponentembed.js/abc124?features=lightbox,pinterest&filtertags=galleryaccess'></script>
HTML,
            'published_at' => now(),
        ]);

        $this->get(route('journal.show', $post->slug))
            ->assertOk()
            ->assertSee('Imagine stepping into a world where every moment is frozen in time.')
            ->assertSee('Location: Sawgrass Lake Park')
            ->assertSee('https://donaldsextonphotography.pic-time.com/-searchread-journal/slideswebcomponentembed.js/abc124?features=lightbox,pinterest&filtertags=galleryaccess', false);
    }

    public function test_pictime_wedding_story_prefers_native_gallery_when_local_media_exists(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/native-story/01-hero.jpg',
            'filename' => '01-hero.jpg',
        ]);

        $gallery = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/native-story/02-gallery.jpg',
            'filename' => '02-gallery.jpg',
        ]);

        $story = WeddingStory::create([
            'title' => 'Native Pic-Time Story',
            'slug' => 'native-pictime-story',
            'status' => 'published',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-native-story',
            'excerpt' => 'A small lead keeps the local gallery grounded.',
            'hero_media_id' => $hero->id,
            'source_markup' => "<template data-pt-type='blog'></template><script src='https://donaldsextonphotography.pic-time.com/-native-story/slideswebcomponentembed.js/abc125?features=lightbox,pinterest&filtertags=galleryaccess'></script>",
            'published_at' => now(),
        ]);

        $story->media()->attach([
            $hero->id => ['role' => 'gallery', 'sort_order' => 0],
            $gallery->id => ['role' => 'gallery', 'sort_order' => 1],
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('/storage/imports/pictime/native-story/02-gallery.jpg', false)
            ->assertDontSee('slideswebcomponentembed.js/abc125', false)
            ->assertDontSee('Open Original Gallery');
    }

    public function test_pictime_journal_post_prefers_native_gallery_when_local_media_exists(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/native-journal/01-hero.jpg',
            'filename' => '01-hero.jpg',
        ]);

        $gallery = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/native-journal/02-gallery.jpg',
            'filename' => '02-gallery.jpg',
        ]);

        $post = JournalPost::create([
            'title' => 'Native Pic-Time Journal',
            'slug' => 'native-pictime-journal',
            'status' => 'published',
            'post_type' => 'engagement',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-native-journal',
            'excerpt' => 'A short lead keeps the local gallery grounded.',
            'hero_media_id' => $hero->id,
            'source_markup' => "<template data-pt-type='blog'></template><script src='https://donaldsextonphotography.pic-time.com/-native-journal/slideswebcomponentembed.js/abc126?features=lightbox,pinterest&filtertags=galleryaccess'></script>",
            'published_at' => now(),
        ]);

        $post->media()->attach([
            $hero->id => ['role' => 'gallery', 'sort_order' => 0],
            $gallery->id => ['role' => 'gallery', 'sort_order' => 1],
        ]);

        $this->get(route('journal.show', $post->slug))
            ->assertOk()
            ->assertSee('/storage/imports/pictime/native-journal/02-gallery.jpg', false)
            ->assertDontSee('slideswebcomponentembed.js/abc126', false)
            ->assertDontSee('Open Original Gallery');
    }

    public function test_shallow_pictime_wedding_story_keeps_gallery_fallback_when_only_one_local_gallery_image_exists(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/shallow-story/01-hero.jpg',
            'filename' => '01-hero.jpg',
        ]);

        $gallery = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/shallow-story/02-gallery.jpg',
            'filename' => '02-gallery.jpg',
        ]);

        $story = WeddingStory::create([
            'title' => 'Shallow Pic-Time Story',
            'slug' => 'shallow-pictime-story',
            'status' => 'published',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-shallow-story',
            'hero_media_id' => $hero->id,
            'source_markup' => "<template data-pt-type='blog'></template><script src='https://donaldsextonphotography.pic-time.com/-shallow-story/slideswebcomponentembed.js/abc127?features=lightbox,pinterest&filtertags=galleryaccess'></script>",
            'published_at' => now(),
        ]);

        $story->media()->attach([
            $hero->id => ['role' => 'gallery', 'sort_order' => 0],
            $gallery->id => ['role' => 'gallery', 'sort_order' => 1],
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertDontSee('/storage/imports/pictime/shallow-story/02-gallery.jpg', false)
            ->assertSee('slideswebcomponentembed.js/abc127', false)
            ->assertSee('Open Full Gallery');
    }

    public function test_shallow_pictime_journal_post_keeps_gallery_fallback_when_only_one_local_gallery_image_exists(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/shallow-journal/01-hero.jpg',
            'filename' => '01-hero.jpg',
        ]);

        $gallery = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/shallow-journal/02-gallery.jpg',
            'filename' => '02-gallery.jpg',
        ]);

        $post = JournalPost::create([
            'title' => 'Shallow Pic-Time Journal',
            'slug' => 'shallow-pictime-journal',
            'status' => 'published',
            'post_type' => 'engagement',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-shallow-journal',
            'hero_media_id' => $hero->id,
            'source_markup' => "<template data-pt-type='blog'></template><script src='https://donaldsextonphotography.pic-time.com/-shallow-journal/slideswebcomponentembed.js/abc128?features=lightbox,pinterest&filtertags=galleryaccess'></script>",
            'published_at' => now(),
        ]);

        $post->media()->attach([
            $hero->id => ['role' => 'gallery', 'sort_order' => 0],
            $gallery->id => ['role' => 'gallery', 'sort_order' => 1],
        ]);

        $this->get(route('journal.show', $post->slug))
            ->assertOk()
            ->assertDontSee('/storage/imports/pictime/shallow-journal/02-gallery.jpg', false)
            ->assertSee('slideswebcomponentembed.js/abc128', false)
            ->assertSee('Open Full Gallery');
    }

    public function test_image_only_pictime_wedding_story_gets_a_clear_gallery_lead(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/gallery-only-story/01-hero.jpg',
            'filename' => '01-hero.jpg',
        ]);

        $galleryA = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/gallery-only-story/02-gallery.jpg',
            'filename' => '02-gallery.jpg',
        ]);

        $galleryB = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/gallery-only-story/03-gallery.jpg',
            'filename' => '03-gallery.jpg',
        ]);

        $story = WeddingStory::create([
            'title' => 'Gallery Only Pic-Time Story',
            'slug' => 'gallery-only-pictime-story',
            'status' => 'published',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-gallery-only-story',
            'hero_media_id' => $hero->id,
            'source_markup' => "<template data-pt-type='blog'></template><script src='https://donaldsextonphotography.pic-time.com/-gallery-only-story/slideswebcomponentembed.js/abc129?features=lightbox,pinterest&filtertags=galleryaccess'></script>",
            'published_at' => now(),
        ]);

        $story->media()->attach([
            $hero->id => ['role' => 'gallery', 'sort_order' => 0],
            $galleryA->id => ['role' => 'gallery', 'sort_order' => 1],
            $galleryB->id => ['role' => 'gallery', 'sort_order' => 2],
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('The gallery from this wedding is shown below.');
    }

    public function test_image_only_pictime_journal_post_gets_a_clear_gallery_lead(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/gallery-only-journal/01-hero.jpg',
            'filename' => '01-hero.jpg',
        ]);

        $galleryA = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/gallery-only-journal/02-gallery.jpg',
            'filename' => '02-gallery.jpg',
        ]);

        $galleryB = Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/gallery-only-journal/03-gallery.jpg',
            'filename' => '03-gallery.jpg',
        ]);

        $post = JournalPost::create([
            'title' => 'Gallery Only Pic-Time Journal',
            'slug' => 'gallery-only-pictime-journal',
            'status' => 'published',
            'post_type' => 'engagement',
            'canonical_url' => 'https://donaldsextonphotography.pic-time.com/-gallery-only-journal',
            'hero_media_id' => $hero->id,
            'source_markup' => "<template data-pt-type='blog'></template><script src='https://donaldsextonphotography.pic-time.com/-gallery-only-journal/slideswebcomponentembed.js/abc130?features=lightbox,pinterest&filtertags=galleryaccess'></script>",
            'published_at' => now(),
        ]);

        $post->media()->attach([
            $hero->id => ['role' => 'gallery', 'sort_order' => 0],
            $galleryA->id => ['role' => 'gallery', 'sort_order' => 1],
            $galleryB->id => ['role' => 'gallery', 'sort_order' => 2],
        ]);

        $this->get(route('journal.show', $post->slug))
            ->assertOk()
            ->assertSee('The gallery from this post is shown below.');
    }
}

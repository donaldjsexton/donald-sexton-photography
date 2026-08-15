<?php

namespace Tests\Feature;

use App\Models\HomepageSetting;
use App\Models\Media;
use App\Models\WeddingStory;
use App\Support\HomeContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The homepage hero and story sections must surface newly published wedding
 * work on their own. Featured picks in homepage settings are a supplement,
 * never a lock that buries the newest stories.
 */
class HomeContentStoryFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_newest_stories_lead_the_pool_ahead_of_older_featured_picks(): void
    {
        $pinnedOlderStory = $this->createStory('Pinned Older Story', now()->subYears(2));
        $newestStory = $this->createStory('Newest Story', now()->subDay());
        $secondNewestStory = $this->createStory('Second Newest Story', now()->subDays(2));
        $thirdNewestStory = $this->createStory('Third Newest Story', now()->subDays(3));

        $content = $this->homeContentWithFeaturedStories([$pinnedOlderStory->id]);

        $this->assertSame($newestStory->id, $content->leadStory()?->id);
        $this->assertSame($secondNewestStory->id, $content->secondStory()?->id);
        $this->assertSame($thirdNewestStory->id, $content->thirdStory()?->id);
        $this->assertSame([
            $newestStory->id,
            $secondNewestStory->id,
            $thirdNewestStory->id,
            $pinnedOlderStory->id,
        ], $content->homeStories()->modelKeys());
    }

    public function test_featured_picks_fill_the_slots_left_over_after_the_newest_stories(): void
    {
        $newestStories = collect(range(1, 3))
            ->map(fn (int $index): WeddingStory => $this->createStory('Recent Story '.$index, now()->subDays($index)));

        $pinnedOlderStory = $this->createStory('Pinned Older Story', now()->subYears(3));
        $this->createStory('Unpinned Middle Story', now()->subYear());

        $content = $this->homeContentWithFeaturedStories([$pinnedOlderStory->id]);

        $this->assertSame(
            $newestStories->pluck('id')->all(),
            $content->homeStories()->take(3)->modelKeys(),
            'The three newest stories must lead the homepage pool.',
        );
        $this->assertSame(
            $pinnedOlderStory->id,
            $content->discoverLeftStory()?->id,
            'The featured pick takes the first slot left after the newest stories.',
        );
    }

    public function test_published_story_without_a_publish_date_is_ranked_by_its_wedding_date(): void
    {
        $olderPublishedStory = $this->createStory('Older Published Story', now()->subMonths(6));
        $recentWeddingStory = $this->createStory('Recent Wedding Story', null, now()->subDays(3));

        $content = $this->homeContentWithFeaturedStories([$olderPublishedStory->id]);

        $this->assertSame($recentWeddingStory->id, $content->leadStory()?->id);
    }

    public function test_homepage_hero_renders_the_newest_story_first(): void
    {
        $pinnedOlderStory = $this->createStory('Pinned Older Story', now()->subYears(2));
        $newestStory = $this->createStory('Newest Story', now()->subDay());

        HomepageSetting::create([
            'featured_story_ids_json' => [$pinnedOlderStory->id],
        ]);

        $markup = $this->get(route('home'))->assertOk()->getContent();

        $this->assertNotFalse(
            strpos($markup, 'alt="'.$newestStory->title.'"'),
            'The newest story must render in the homepage hero.',
        );
        $this->assertLessThan(
            strpos($markup, 'alt="'.$pinnedOlderStory->title.'"'),
            strpos($markup, 'alt="'.$newestStory->title.'"'),
            'The newest story must render ahead of the older featured pick.',
        );
    }

    /**
     * @param  array<int, int>  $featuredStoryIds
     */
    private function homeContentWithFeaturedStories(array $featuredStoryIds): HomeContent
    {
        return new HomeContent(HomepageSetting::create([
            'featured_story_ids_json' => $featuredStoryIds,
        ]));
    }

    private function createStory(string $title, ?Carbon $publishedAt, ?Carbon $eventDate = null): WeddingStory
    {
        $slug = Str::slug($title);

        $media = Media::create([
            'disk' => 'public',
            'path' => 'media/test/'.$slug.'.jpg',
            'filename' => $slug.'.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1200,
            'height' => 1600,
        ]);

        return WeddingStory::create([
            'title' => $title,
            'slug' => $slug,
            'status' => 'published',
            'excerpt' => $title.' excerpt.',
            'published_at' => $publishedAt,
            'event_date' => $eventDate,
            'hero_media_id' => $media->id,
        ]);
    }
}

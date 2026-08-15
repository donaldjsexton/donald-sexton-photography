<?php

namespace Tests\Feature;

use App\Models\JournalPost;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicListingOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_wedding_stories_index_lists_active_stories_newest_first(): void
    {
        $old = WeddingStory::create([
            'title' => 'Old Archive Wedding',
            'slug' => 'old-archive-wedding',
            'status' => 'published',
            'body' => '<p>Old body.</p>',
            'published_at' => now()->subYears(6),
        ]);

        $undated = WeddingStory::create([
            'title' => 'Freshly Added Wedding',
            'slug' => 'freshly-added-wedding',
            'status' => 'published',
            'body' => '<p>Fresh body.</p>',
            'published_at' => null,
        ]);

        $recent = WeddingStory::create([
            'title' => 'Recent Wedding',
            'slug' => 'recent-wedding',
            'status' => 'published',
            'body' => '<p>Recent body.</p>',
            'published_at' => now()->subDay(),
        ]);

        WeddingStory::create([
            'title' => 'Archived Wedding',
            'slug' => 'archived-wedding',
            'status' => 'archived',
            'body' => '<p>Archived body.</p>',
            'published_at' => now()->subDays(2),
        ]);

        $response = $this->get(route('weddings.index'));

        $response->assertOk();

        $stories = $response->viewData('stories');

        $this->assertSame(
            [$undated->id, $recent->id, $old->id],
            $stories->pluck('id')->all(),
        );
    }

    public function test_journal_index_lists_active_posts_newest_first(): void
    {
        $old = JournalPost::create([
            'title' => 'Old Archive Post',
            'slug' => 'old-archive-post',
            'status' => 'published',
            'body' => '<p>Old body.</p>',
            'published_at' => now()->subYears(6),
        ]);

        $undated = JournalPost::create([
            'title' => 'Freshly Added Post',
            'slug' => 'freshly-added-post',
            'status' => 'published',
            'body' => '<p>Fresh body.</p>',
            'published_at' => null,
        ]);

        $recent = JournalPost::create([
            'title' => 'Recent Post',
            'slug' => 'recent-post',
            'status' => 'published',
            'body' => '<p>Recent body.</p>',
            'published_at' => now()->subDay(),
        ]);

        JournalPost::create([
            'title' => 'Archived Post',
            'slug' => 'archived-post',
            'status' => 'archived',
            'body' => '<p>Archived body.</p>',
            'published_at' => now()->subDays(2),
        ]);

        $response = $this->get(route('journal.index'));

        $response->assertOk();

        $posts = $response->viewData('posts');

        $this->assertSame(
            [$undated->id, $recent->id, $old->id],
            $posts->pluck('id')->all(),
        );
    }
}

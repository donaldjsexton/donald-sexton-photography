<?php

namespace Tests\Feature\Observers;

use App\Jobs\BackfillSeoMetadata;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SeoMetadataObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.auto_seo', true);
        Bus::fake();
    }

    public function test_publishing_a_story_without_seo_queues_a_backfill(): void
    {
        WeddingStory::create([
            'title' => 'A Wedding',
            'slug' => 'a-wedding',
            'status' => 'published',
        ]);

        Bus::assertDispatched(BackfillSeoMetadata::class);
    }

    public function test_saving_a_venue_queues_a_backfill(): void
    {
        Venue::factory()->create(['seo_title' => null, 'seo_description' => null]);

        Bus::assertDispatched(BackfillSeoMetadata::class);
    }

    public function test_disabled_flag_skips_dispatch(): void
    {
        config()->set('services.anthropic.auto_seo', false);

        WeddingStory::create([
            'title' => 'A Wedding',
            'slug' => 'a-wedding',
            'status' => 'published',
        ]);

        Bus::assertNotDispatched(BackfillSeoMetadata::class);
    }

    public function test_draft_story_does_not_queue(): void
    {
        WeddingStory::create([
            'title' => 'Draft Wedding',
            'slug' => 'draft-wedding',
            'status' => 'draft',
        ]);

        Bus::assertNotDispatched(BackfillSeoMetadata::class);
    }

    public function test_story_with_complete_seo_does_not_queue(): void
    {
        WeddingStory::create([
            'title' => 'A Wedding',
            'slug' => 'a-wedding',
            'status' => 'published',
            'seo_title' => 'Title',
            'seo_description' => 'Description.',
        ]);

        Bus::assertNotDispatched(BackfillSeoMetadata::class);
    }
}

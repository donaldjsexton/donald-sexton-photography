<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillVenueLinksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_story_to_existing_venue_by_name(): void
    {
        $venue = Venue::factory()->create(['name' => 'Sandpearl Resort']);

        $story = WeddingStory::create([
            'title' => 'Beach Wedding',
            'slug' => 'beach-wedding',
            'status' => 'published',
            'location_name' => 'sandpearl resort',
            'hero_media_id' => null,
        ]);

        $this->artisan('venue:backfill-links')->assertSuccessful();

        $this->assertSame($venue->id, $story->fresh()->venue_id);
    }

    public function test_reports_candidates_without_create_flag(): void
    {
        $story = WeddingStory::create([
            'title' => 'Mystery Wedding',
            'slug' => 'mystery-wedding',
            'status' => 'published',
            'location_name' => 'Unlisted Manor',
        ]);

        $this->artisan('venue:backfill-links')
            ->expectsOutputToContain('Unlisted Manor')
            ->assertSuccessful();

        $this->assertNull($story->fresh()->venue_id);
        $this->assertSame(0, Venue::query()->count());
    }

    public function test_create_flag_creates_and_links_venue(): void
    {
        $story = WeddingStory::create([
            'title' => 'Manor Wedding',
            'slug' => 'manor-wedding',
            'status' => 'published',
            'location_name' => 'Unlisted Manor',
            'city' => 'Dunedin',
            'state' => 'FL',
        ]);

        $this->artisan('venue:backfill-links', ['--create' => true])->assertSuccessful();

        $venue = Venue::query()->where('name', 'Unlisted Manor')->first();
        $this->assertNotNull($venue);
        $this->assertSame('Dunedin', $venue->city);
        $this->assertSame($venue->id, $story->fresh()->venue_id);
    }

    public function test_dry_run_changes_nothing(): void
    {
        Venue::factory()->create(['name' => 'Sandpearl Resort']);

        $story = WeddingStory::create([
            'title' => 'Beach Wedding',
            'slug' => 'beach-wedding',
            'status' => 'published',
            'location_name' => 'Sandpearl Resort',
        ]);

        $this->artisan('venue:backfill-links', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($story->fresh()->venue_id);
    }
}

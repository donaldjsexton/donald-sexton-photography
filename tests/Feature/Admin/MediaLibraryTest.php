<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\User;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_admin(): void
    {
        $this->get(route('admin.media.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_stats_recent_strip_and_filters(): void
    {
        $user = User::factory()->create();

        $orphan = Media::create([
            'disk' => 'public',
            'path' => 'media/orphan.jpg',
            'filename' => 'orphan.jpg',
            'alt_text' => 'Has alt',
        ]);

        $used = Media::create([
            'disk' => 'public',
            'path' => 'media/used.jpg',
            'filename' => 'used.jpg',
            'alt_text' => null,
        ]);

        $story = WeddingStory::create([
            'title' => 'Smith Wedding',
            'slug' => 'smith-wedding',
            'status' => 'published',
            'story_type' => 'wedding',
        ]);

        $story->media()->attach($used->id, ['role' => 'gallery', 'sort_order' => 0]);

        $response = $this->actingAs($user)->get(route('admin.media.index'));

        $response->assertOk()
            ->assertSee('Photo library')
            ->assertSee('Fresh uploads')
            ->assertSee('Total')
            ->assertSee('Orphaned')
            ->assertSee('Missing alt')
            ->assertSee('orphan.jpg')
            ->assertSee('used.jpg')
            ->assertSee('Smith Wedding'); // usage chip

        // Stat values
        $response->assertSee('2'); // total
    }

    public function test_filter_unused_excludes_used_media(): void
    {
        $user = User::factory()->create();

        $used = Media::create([
            'disk' => 'public',
            'path' => 'media/used.jpg',
            'filename' => 'used.jpg',
        ]);

        $orphan = Media::create([
            'disk' => 'public',
            'path' => 'media/orphan.jpg',
            'filename' => 'orphan.jpg',
        ]);

        $story = WeddingStory::create([
            'title' => 'Used Story',
            'slug' => 'used-story',
            'status' => 'published',
            'story_type' => 'wedding',
        ]);

        $story->media()->attach($used->id, ['role' => 'gallery', 'sort_order' => 0]);

        $response = $this->actingAs($user)->get(route('admin.media.index', ['filter' => 'unused']));

        $response->assertOk()
            ->assertSee('orphan.jpg')
            ->assertDontSee('used.jpg');
    }

    public function test_filter_missing_alt_finds_media_without_alt(): void
    {
        $user = User::factory()->create();

        Media::create([
            'disk' => 'public',
            'path' => 'media/has-alt.jpg',
            'filename' => 'has-alt.jpg',
            'alt_text' => 'Described',
        ]);

        Media::create([
            'disk' => 'public',
            'path' => 'media/no-alt.jpg',
            'filename' => 'no-alt.jpg',
            'alt_text' => null,
        ]);

        $response = $this->actingAs($user)->get(route('admin.media.index', ['filter' => 'missing-alt']));

        $response->assertOk()
            ->assertSee('no-alt.jpg')
            ->assertDontSee('has-alt.jpg');
    }

    public function test_search_matches_filename(): void
    {
        $user = User::factory()->create();

        Media::create([
            'disk' => 'public',
            'path' => 'media/sunrise.jpg',
            'filename' => 'sunrise.jpg',
        ]);

        Media::create([
            'disk' => 'public',
            'path' => 'media/reception.jpg',
            'filename' => 'reception.jpg',
        ]);

        $response = $this->actingAs($user)->get(route('admin.media.index', ['q' => 'sunrise']));

        $response->assertOk()
            ->assertSee('sunrise.jpg')
            ->assertDontSee('reception.jpg');
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\User;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function makeStory(?int $heroId = null): WeddingStory
    {
        return WeddingStory::create([
            'title' => 'Smith Wedding',
            'slug' => 'smith-wedding',
            'status' => 'draft',
            'story_type' => 'wedding',
            'hero_media_id' => $heroId,
        ]);
    }

    private function makeMedia(string $name): Media
    {
        return Media::create([
            'disk' => 'public',
            'path' => "media/{$name}.jpg",
            'filename' => "{$name}.jpg",
        ]);
    }

    public function test_edit_page_renders_gallery_panel(): void
    {
        $user = User::factory()->create();
        $story = $this->makeStory();
        $media = $this->makeMedia('photo-1');

        $story->media()->attach($media->id, ['role' => 'gallery', 'sort_order' => 0]);

        $response = $this->actingAs($user)->get(route('admin.wedding-stories.edit', $story));

        $response->assertOk()
            ->assertSee('story-gallery', false)
            ->assertSee('photo-1.jpg')
            ->assertSee('Photos');
    }

    public function test_attach_appends_new_media_to_gallery(): void
    {
        $user = User::factory()->create();
        $story = $this->makeStory();
        [$a, $b] = [$this->makeMedia('a'), $this->makeMedia('b')];

        $response = $this->actingAs($user)->postJson(
            route('admin.wedding-stories.media.attach', $story),
            ['media_ids' => [$a->id, $b->id]]
        );

        $response->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('items.0.id', $a->id)
            ->assertJsonPath('items.1.id', $b->id);

        $this->assertDatabaseHas('mediables', ['media_id' => $a->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('mediables', ['media_id' => $b->id, 'sort_order' => 1]);
    }

    public function test_attach_skips_already_attached_media(): void
    {
        $user = User::factory()->create();
        $story = $this->makeStory();
        $a = $this->makeMedia('a');

        $story->media()->attach($a->id, ['role' => 'gallery', 'sort_order' => 0]);

        $response = $this->actingAs($user)->postJson(
            route('admin.wedding-stories.media.attach', $story),
            ['media_ids' => [$a->id]]
        );

        $response->assertOk()->assertJsonPath('count', 1);
        $this->assertSame(1, $story->media()->count());
    }

    public function test_reorder_persists_new_sort_order(): void
    {
        $user = User::factory()->create();
        $story = $this->makeStory();
        $a = $this->makeMedia('a');
        $b = $this->makeMedia('b');
        $c = $this->makeMedia('c');

        $story->media()->attach($a->id, ['role' => 'gallery', 'sort_order' => 0]);
        $story->media()->attach($b->id, ['role' => 'gallery', 'sort_order' => 1]);
        $story->media()->attach($c->id, ['role' => 'gallery', 'sort_order' => 2]);

        $response = $this->actingAs($user)->patchJson(
            route('admin.wedding-stories.media.reorder', $story),
            ['media_ids' => [$c->id, $a->id, $b->id]]
        );

        $response->assertOk()
            ->assertJsonPath('items.0.id', $c->id)
            ->assertJsonPath('items.1.id', $a->id)
            ->assertJsonPath('items.2.id', $b->id);
    }

    public function test_detach_removes_media_and_clears_hero_when_removed_item_was_hero(): void
    {
        $user = User::factory()->create();
        $a = $this->makeMedia('a');
        $b = $this->makeMedia('b');
        $story = $this->makeStory($a->id);

        $story->media()->attach($a->id, ['role' => 'hero', 'sort_order' => 0]);
        $story->media()->attach($b->id, ['role' => 'gallery', 'sort_order' => 1]);

        $response = $this->actingAs($user)->deleteJson(
            route('admin.wedding-stories.media.detach', ['weddingStory' => $story, 'media' => $a])
        );

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.id', $b->id)
            ->assertJsonPath('hero_media_id', null);

        $this->assertNull($story->fresh()->hero_media_id);
    }

    public function test_set_hero_updates_owner_and_attaches_if_missing(): void
    {
        $user = User::factory()->create();
        $story = $this->makeStory();
        $a = $this->makeMedia('a');

        $response = $this->actingAs($user)->postJson(
            route('admin.wedding-stories.media.hero', ['weddingStory' => $story, 'media' => $a])
        );

        $response->assertOk()
            ->assertJsonPath('hero_media_id', $a->id)
            ->assertJsonPath('items.0.is_hero', true);

        $this->assertSame($a->id, $story->fresh()->hero_media_id);
    }
}

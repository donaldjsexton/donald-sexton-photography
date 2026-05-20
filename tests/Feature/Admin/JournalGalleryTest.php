<?php

namespace Tests\Feature\Admin;

use App\Models\JournalPost;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(?int $heroId = null): JournalPost
    {
        return JournalPost::create([
            'title' => 'Spring tips',
            'slug' => 'spring-tips',
            'status' => 'draft',
            'post_type' => 'advice',
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

    public function test_attach_endpoint_appends_media(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost();
        $a = $this->makeMedia('journal-a');

        $response = $this->actingAs($user)->postJson(
            route('admin.journal-posts.media.attach', $post),
            ['media_ids' => [$a->id]]
        );

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.id', $a->id);
    }

    public function test_set_hero_endpoint_promotes_media(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost();
        $a = $this->makeMedia('journal-hero');

        $post->media()->attach($a->id, ['role' => 'gallery', 'sort_order' => 0]);

        $response = $this->actingAs($user)->postJson(
            route('admin.journal-posts.media.hero', ['journalPost' => $post, 'media' => $a])
        );

        $response->assertOk()
            ->assertJsonPath('hero_media_id', $a->id);

        $this->assertSame($a->id, $post->fresh()->hero_media_id);
    }

    public function test_reorder_endpoint_updates_sort_order(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost();
        $a = $this->makeMedia('j-a');
        $b = $this->makeMedia('j-b');

        $post->media()->attach($a->id, ['role' => 'gallery', 'sort_order' => 0]);
        $post->media()->attach($b->id, ['role' => 'gallery', 'sort_order' => 1]);

        $response = $this->actingAs($user)->patchJson(
            route('admin.journal-posts.media.reorder', $post),
            ['media_ids' => [$b->id, $a->id]]
        );

        $response->assertOk()
            ->assertJsonPath('items.0.id', $b->id)
            ->assertJsonPath('items.1.id', $a->id);
    }
}

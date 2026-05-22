<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\JournalPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalPostBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPost(string $slug = 'planning-your-day'): JournalPost
    {
        return JournalPost::create([
            'title' => 'Planning Your Day',
            'slug' => $slug,
            'status' => 'published',
            'post_type' => 'advice',
            'body' => '<p>Imported body copy.</p>',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_journal_post_renders_blocks_after_the_body(): void
    {
        $post = $this->publishedPost();

        Block::factory()->type('rich_text')->create([
            'blockable_id' => $post->id,
            'blockable_type' => $post->getMorphClass(),
            'body' => '<p>An extra composed section.</p>',
            'sort_order' => 0,
        ]);

        $this->get('/journal/planning-your-day')
            ->assertOk()
            ->assertSee('Imported body copy.')
            ->assertSee('An extra composed section.');
    }

    public function test_admin_can_add_a_block_to_a_journal_post(): void
    {
        $user = User::factory()->create();
        $post = $this->publishedPost();

        $this->actingAs($user)->post(route('admin.journal-posts.blocks.store', $post), [
            'type' => 'quote',
            'body' => 'A favourite line from the day.',
        ])->assertRedirect(route('admin.journal-posts.edit', $post));

        $this->assertDatabaseHas('blocks', [
            'blockable_id' => $post->id,
            'blockable_type' => $post->getMorphClass(),
            'type' => 'quote',
        ]);
    }

    public function test_journal_block_management_requires_authentication(): void
    {
        $post = $this->publishedPost();

        $this->post(route('admin.journal-posts.blocks.store', $post), ['type' => 'rich_text'])
            ->assertRedirect(route('admin.login'));
    }
}

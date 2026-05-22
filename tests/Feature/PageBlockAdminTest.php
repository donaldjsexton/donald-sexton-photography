<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBlockAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_management_requires_authentication(): void
    {
        $page = Page::factory()->create();

        $this->post(route('admin.pages.blocks.store', $page), ['type' => 'rich_text'])
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_add_a_block_to_a_page(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.pages.blocks.store', $page), [
            'type' => 'rich_text',
            'heading' => 'Our Approach',
            'body' => '<p>How we work.</p>',
        ]);

        $response->assertRedirect(route('admin.pages.edit', $page));

        $this->assertDatabaseHas('blocks', [
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
            'type' => 'rich_text',
            'heading' => 'Our Approach',
        ]);
    }

    public function test_admin_can_update_a_block(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->create();
        $block = Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
        ]);

        $this->actingAs($user)->put(route('admin.pages.blocks.update', [$page, $block]), [
            'type' => 'cta',
            'heading' => 'Book your date',
            'body' => 'Let us talk.',
            'data' => ['primary_url' => 'https://example.com/inquire', 'primary_label' => 'Inquire'],
            'sort_order' => 5,
            'is_visible' => '1',
        ])->assertRedirect(route('admin.pages.edit', $page));

        $block->refresh();
        $this->assertSame('cta', $block->type);
        $this->assertSame('Book your date', $block->heading);
        $this->assertSame(5, $block->sort_order);
        $this->assertSame('https://example.com/inquire', $block->data['primary_url']);
    }

    public function test_admin_can_hide_a_block(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->create();
        $block = Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
            'is_visible' => true,
        ]);

        $this->actingAs($user)->put(route('admin.pages.blocks.update', [$page, $block]), [
            'type' => $block->type,
            'is_visible' => '0',
        ])->assertRedirect(route('admin.pages.edit', $page));

        $this->assertFalse($block->refresh()->is_visible);
    }

    public function test_admin_can_delete_a_block(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->create();
        $block = Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
        ]);

        $this->actingAs($user)->delete(route('admin.pages.blocks.destroy', [$page, $block]))
            ->assertRedirect(route('admin.pages.edit', $page));

        $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
    }

    public function test_admin_can_attach_and_detach_block_media(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->create();
        $block = Block::factory()->type('gallery')->create([
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
        ]);

        $media = Media::create([
            'disk' => 'public',
            'path' => 'media/2026/05/gallery.jpg',
            'filename' => 'gallery.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $this->actingAs($user)->post(route('admin.pages.blocks.media.attach', [$page, $block]), [
            'media_id' => $media->id,
        ])->assertRedirect(route('admin.pages.edit', $page));

        $this->assertSame(1, $block->media()->count());

        $this->actingAs($user)->delete(route('admin.pages.blocks.media.detach', [$page, $block, $media]))
            ->assertRedirect(route('admin.pages.edit', $page));

        $this->assertSame(0, $block->media()->count());
    }

    public function test_admin_cannot_manage_a_block_through_a_mismatched_page(): void
    {
        $user = User::factory()->create();
        $owner = Page::factory()->create();
        $other = Page::factory()->create();
        $block = Block::factory()->create([
            'blockable_id' => $owner->id,
            'blockable_type' => $owner->getMorphClass(),
        ]);

        $this->actingAs($user)->put(route('admin.pages.blocks.update', [$other, $block]), [
            'type' => 'rich_text',
        ])->assertNotFound();
    }

    public function test_page_edit_screen_shows_the_block_manager(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->create();
        Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
            'heading' => 'Existing Block Heading',
        ]);

        $this->actingAs($user)->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertSee('Page Blocks')
            ->assertSee('Existing Block Heading');
    }
}

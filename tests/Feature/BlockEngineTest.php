<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Media;
use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BlockEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_relation_returns_only_visible_blocks_in_sort_order(): void
    {
        $page = Page::factory()->create();

        $second = Block::factory()->type('rich_text')->create([
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
            'sort_order' => 2,
        ]);
        $first = Block::factory()->type('rich_text')->create([
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
            'sort_order' => 1,
        ]);
        $hidden = Block::factory()->type('rich_text')->hidden()->create([
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
            'sort_order' => 3,
        ]);

        $visible = $page->blocks()->get();
        $this->assertSame([$first->id, $second->id], $visible->pluck('id')->all());

        $all = $page->allBlocks()->get();
        $this->assertSame([$first->id, $second->id, $hidden->id], $all->pluck('id')->all());
    }

    public function test_block_attaches_polymorphic_media(): void
    {
        $block = Block::factory()->type('gallery')->create();

        $media = Media::create([
            'disk' => 'public',
            'path' => 'media/2026/05/example.jpg',
            'filename' => 'example.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $block->media()->attach($media->id, ['role' => 'gallery', 'sort_order' => 0]);

        $this->assertSame(1, $block->media()->count());
        $this->assertTrue($block->media->contains($media));
    }

    public function test_published_page_renders_through_block_engine_via_slug(): void
    {
        $page = Page::factory()->create([
            'slug' => 'studio-services',
            'body' => '<p>Legacy body should be ignored.</p>',
        ]);

        Block::factory()->type('rich_text')->create([
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
            'body' => '<p>Composed block content here.</p>',
            'sort_order' => 0,
        ]);

        $response = $this->get('/studio-services');

        $response->assertOk();
        $response->assertSee('Composed block content here.');
        $response->assertDontSee('Legacy body should be ignored.');
    }

    public function test_page_without_blocks_falls_back_to_legacy_body(): void
    {
        Page::factory()->create([
            'slug' => 'legacy-only',
            'body' => '<p>Only the legacy body exists.</p>',
        ]);

        $this->get('/legacy-only')
            ->assertOk()
            ->assertSee('Only the legacy body exists.');
    }

    public function test_unknown_block_type_falls_back_to_rich_text_renderer(): void
    {
        $page = Page::factory()->create(['slug' => 'mystery-page']);

        Block::factory()->create([
            'blockable_id' => $page->id,
            'blockable_type' => $page->getMorphClass(),
            'type' => 'not_a_real_type',
            'body' => '<p>Rendered by the safe fallback.</p>',
            'sort_order' => 0,
        ]);

        $this->get('/mystery-page')
            ->assertOk()
            ->assertSee('Rendered by the safe fallback.');
    }

    public function test_draft_page_slug_does_not_render_and_returns_404(): void
    {
        Page::factory()->draft()->create(['slug' => 'hidden-draft']);

        $this->get('/hidden-draft')->assertNotFound();
    }

    public function test_legacy_redirect_takes_precedence_over_unpublished_slug(): void
    {
        Page::factory()->draft()->create(['slug' => 'moved-page']);

        Redirect::query()->create([
            'from_path' => '/moved-page',
            'to_path' => '/weddings',
            'status_code' => 301,
            'source' => 'manual',
        ]);

        $this->get('/moved-page')->assertRedirect('/weddings');
    }

    public function test_every_registered_block_type_renders_without_error(): void
    {
        $page = Page::factory()->create(['slug' => 'kitchen-sink']);

        $media = Media::create([
            'disk' => 'public',
            'path' => 'media/2026/05/render.jpg',
            'filename' => 'render.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $sort = 0;

        foreach (array_keys((array) config('blocks.types')) as $type) {
            $block = Block::factory()->type($type)->create([
                'blockable_id' => $page->id,
                'blockable_type' => $page->getMorphClass(),
                'heading' => 'Heading '.$type,
                'body' => '<p>Body '.$type.'</p>',
                'sort_order' => $sort++,
            ]);

            $block->media()->attach($media->id, ['role' => 'gallery', 'sort_order' => 0]);
        }

        $this->get('/kitchen-sink')->assertOk();
    }

    public function test_backfill_command_wraps_body_into_rich_text_block_idempotently(): void
    {
        $page = Page::factory()->create([
            'slug' => 'backfill-target',
            'body' => '<p>Body to migrate into a block.</p>',
        ]);

        Artisan::call('pages:backfill-blocks');

        $blocks = $page->allBlocks()->get();
        $this->assertCount(1, $blocks);
        $this->assertSame('rich_text', $blocks->first()->type);
        $this->assertStringContainsString('Body to migrate into a block.', (string) $blocks->first()->body);

        Artisan::call('pages:backfill-blocks');

        $this->assertSame(1, $page->allBlocks()->count());
    }
}

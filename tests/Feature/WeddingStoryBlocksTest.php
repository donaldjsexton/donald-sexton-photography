<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeddingStoryBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_wedding_story_renders_sequence_through_the_block_engine(): void
    {
        $story = WeddingStory::create([
            'title' => 'Coastal Wedding',
            'slug' => 'coastal-wedding',
            'status' => 'published',
            'story_type' => 'wedding',
            'published_at' => now()->subDay(),
        ]);

        Block::factory()->type('rich_text')->create([
            'blockable_id' => $story->id,
            'blockable_type' => $story->getMorphClass(),
            'body' => '<p>The vows happened at golden hour.</p>',
            'sort_order' => 0,
        ]);
        Block::factory()->type('quote')->create([
            'blockable_id' => $story->id,
            'blockable_type' => $story->getMorphClass(),
            'body' => 'It was the best day of our lives.',
            'sort_order' => 1,
        ]);

        $response = $this->get('/weddings/coastal-wedding');

        $response->assertOk();
        $response->assertSee('The vows happened at golden hour.');
        $response->assertSee('It was the best day of our lives.');
    }

    public function test_legacy_story_blocks_migrate_into_mapped_block_types(): void
    {
        $story = WeddingStory::create([
            'title' => 'Legacy Sequence',
            'slug' => 'legacy-sequence',
            'status' => 'published',
            'story_type' => 'wedding',
            'published_at' => now()->subDay(),
        ]);

        $now = now();
        DB::table('story_blocks')->insert([
            ['wedding_story_id' => $story->id, 'block_type' => 'quote', 'heading' => 'A', 'body' => 'Quote body', 'settings_json' => null, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['wedding_story_id' => $story->id, 'block_type' => 'full_bleed_image', 'heading' => 'B', 'body' => null, 'settings_json' => null, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['wedding_story_id' => $story->id, 'block_type' => 'carousel', 'heading' => 'C', 'body' => null, 'settings_json' => json_encode(['frame_one_label' => 'x']), 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['wedding_story_id' => $story->id, 'block_type' => 'mystery', 'heading' => 'D', 'body' => 'Fallback', 'settings_json' => null, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $migration = require database_path('migrations/2026_05_22_134047_migrate_story_blocks_into_blocks.php');
        $migration->up();

        $types = $story->allBlocks()->pluck('type')->all();
        $this->assertSame(['quote', 'full_bleed', 'gallery', 'rich_text'], $types);

        // Idempotent: a story that already has blocks is not duplicated.
        $migration->up();
        $this->assertSame(4, $story->allBlocks()->count());
    }
}

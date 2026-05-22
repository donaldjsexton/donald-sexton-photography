<?php

use App\Models\WeddingStory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copy legacy story_blocks rows into the unified blocks table so wedding
     * stories render through the block engine. Idempotent per story: a story
     * that already has blocks is skipped, so re-running never duplicates.
     */
    public function up(): void
    {
        if (! Schema::hasTable('story_blocks')) {
            return;
        }

        $morphType = (new WeddingStory)->getMorphClass();
        $now = now();

        DB::table('story_blocks')
            ->orderBy('wedding_story_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('wedding_story_id')
            ->each(function ($storyBlocks, $storyId) use ($morphType, $now): void {
                $alreadyMigrated = DB::table('blocks')
                    ->where('blockable_type', $morphType)
                    ->where('blockable_id', $storyId)
                    ->exists();

                if ($alreadyMigrated) {
                    return;
                }

                $rows = $storyBlocks->map(fn ($block): array => [
                    'blockable_type' => $morphType,
                    'blockable_id' => $storyId,
                    'site_id' => null,
                    'type' => $this->mapType($block->block_type),
                    'heading' => $block->heading,
                    'subheading' => null,
                    'body' => $block->body,
                    'data' => $block->settings_json,
                    'is_visible' => true,
                    'sort_order' => $block->sort_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('blocks')->insert($rows);
            });
    }

    /**
     * Remove only the blocks that were synthesised from wedding stories.
     */
    public function down(): void
    {
        DB::table('blocks')
            ->where('blockable_type', (new WeddingStory)->getMorphClass())
            ->delete();
    }

    private function mapType(?string $legacyType): string
    {
        return match ($legacyType) {
            'quote' => 'quote',
            'image_pair' => 'image_pair',
            'full_bleed_image' => 'full_bleed',
            'carousel' => 'gallery',
            'spacer' => 'spacer',
            default => 'rich_text',
        };
    }
};

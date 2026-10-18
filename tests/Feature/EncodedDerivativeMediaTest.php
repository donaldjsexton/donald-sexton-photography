<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EncodedDerivativeMediaTest extends TestCase
{
    use RefreshDatabase;

    private function makeStory(string $slug = 'dupe-story'): WeddingStory
    {
        return WeddingStory::create([
            'title' => 'Dupe Story',
            'slug' => $slug,
            'status' => 'draft',
            'story_type' => 'wedding',
        ]);
    }

    private function makeMedia(string $path): Media
    {
        return Media::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => basename($path),
        ]);
    }

    public function test_attach_existing_skips_webp_sibling_of_an_original(): void
    {
        Storage::fake('public');

        $story = $this->makeStory();
        $directory = "imports/pictime/{$story->slug}";

        Storage::disk('public')->put("{$directory}/01-photo-abc123.jpg", 'fake-jpg-bytes');
        Storage::disk('public')->put("{$directory}/01-photo-abc123.webp", 'fake-webp-bytes');

        Artisan::call('pictime:attach-existing', [
            '--slug' => [$story->slug],
            '--apply' => true,
        ]);

        $attached = $story->media()->pluck('path')->all();

        $this->assertCount(1, $attached);
        $this->assertSame("{$directory}/01-photo-abc123.jpg", $attached[0]);
        $this->assertDatabaseMissing('media', ['path' => "{$directory}/01-photo-abc123.webp"]);
    }

    public function test_attach_existing_keeps_a_standalone_webp_original(): void
    {
        Storage::fake('public');

        $story = $this->makeStory('webp-original-story');
        $directory = "imports/pictime/{$story->slug}";

        Storage::disk('public')->put("{$directory}/01-photo-abc123.webp", 'fake-webp-bytes');

        Artisan::call('pictime:attach-existing', [
            '--slug' => [$story->slug],
            '--apply' => true,
        ]);

        $this->assertSame(1, $story->media()->count());
        $this->assertDatabaseHas('media', ['path' => "{$directory}/01-photo-abc123.webp"]);
    }

    public function test_detach_encoded_duplicates_removes_webp_row_and_keeps_original(): void
    {
        $story = $this->makeStory();
        $jpg = $this->makeMedia('imports/pictime/dupe-story/01-photo-abc123.jpg');
        $webp = $this->makeMedia('imports/pictime/dupe-story/01-photo-abc123.webp');

        $story->media()->attach($jpg->id, ['role' => 'gallery', 'sort_order' => 0]);
        $story->media()->attach($webp->id, ['role' => 'gallery', 'sort_order' => 1]);

        Artisan::call('media:detach-encoded-duplicates', ['--apply' => true]);

        $this->assertDatabaseMissing('media', ['id' => $webp->id]);
        $this->assertDatabaseHas('mediables', ['media_id' => $jpg->id]);
        $this->assertDatabaseMissing('mediables', ['media_id' => $webp->id]);
        $this->assertSame(1, $story->media()->count());
    }

    public function test_detach_encoded_duplicates_keeps_standalone_and_hero_webp_rows(): void
    {
        $story = $this->makeStory();
        $standalone = $this->makeMedia('imports/pictime/dupe-story/02-photo-def456.webp');
        $heroWebp = $this->makeMedia('imports/pictime/dupe-story/03-photo-ghi789.webp');
        $heroJpg = $this->makeMedia('imports/pictime/dupe-story/03-photo-ghi789.jpg');

        $story->media()->attach($standalone->id, ['role' => 'gallery', 'sort_order' => 0]);
        $story->media()->attach($heroWebp->id, ['role' => 'gallery', 'sort_order' => 1]);
        $story->media()->attach($heroJpg->id, ['role' => 'gallery', 'sort_order' => 2]);
        $story->hero_media_id = $heroWebp->id;
        $story->save();

        Artisan::call('media:detach-encoded-duplicates', ['--apply' => true]);

        $this->assertDatabaseHas('media', ['id' => $standalone->id]);
        $this->assertDatabaseHas('media', ['id' => $heroWebp->id]);
        $this->assertSame($heroWebp->id, $story->fresh()->hero_media_id);
    }

    public function test_detach_encoded_duplicates_is_dry_run_without_apply(): void
    {
        $story = $this->makeStory();
        $jpg = $this->makeMedia('imports/pictime/dupe-story/01-photo-abc123.jpg');
        $webp = $this->makeMedia('imports/pictime/dupe-story/01-photo-abc123.webp');

        $story->media()->attach($jpg->id, ['role' => 'gallery', 'sort_order' => 0]);
        $story->media()->attach($webp->id, ['role' => 'gallery', 'sort_order' => 1]);

        Artisan::call('media:detach-encoded-duplicates');

        $this->assertDatabaseHas('media', ['id' => $webp->id]);
        $this->assertSame(2, $story->media()->count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\JournalPost;
use App\Models\Media;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PicTimeAttachExistingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_files_without_writing(): void
    {
        Storage::fake('public');

        $story = $this->makePicTimeStory('autumn-vineyard');

        $this->seedDirectory($story->slug, ['01-first.jpg', '02-second.jpg', '03-third.jpg']);

        $exitCode = Artisan::call('pictime:attach-existing', [
            '--slug' => [$story->slug],
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Dry run', $output);
        $this->assertStringContainsString('3 files (0 existing, 3 new)', $output);

        $this->assertSame(0, Media::query()->count(), 'Dry run must not create Media rows.');
        $this->assertSame(0, $story->fresh()->media()->count(), 'Dry run must not attach pivot rows.');
        $this->assertNull($story->fresh()->hero_media_id, 'Dry run must not set hero_media_id.');
    }

    public function test_apply_creates_media_rows_and_attaches_in_filename_order(): void
    {
        Storage::fake('public');

        $story = $this->makePicTimeStory('seaside-elopement');

        $this->seedDirectory($story->slug, ['03-third.jpg', '01-first.jpg', '02-second.jpg']);

        $exitCode = Artisan::call('pictime:attach-existing', [
            '--slug' => [$story->slug],
            '--apply' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $story->refresh();
        $attached = $story->media()->get();

        $this->assertCount(3, $attached);
        $this->assertSame(
            ['01-first.jpg', '02-second.jpg', '03-third.jpg'],
            $attached->pluck('filename')->all(),
            'Attached media should be ordered by filename.'
        );

        $heroFilename = $story->heroMedia?->filename;
        $this->assertSame('01-first.jpg', $heroFilename, 'Hero should default to the first file.');

        $firstMedia = $attached->firstWhere('filename', '01-first.jpg');
        $this->assertSame('public', $firstMedia->disk);
        $this->assertSame("imports/pictime/{$story->slug}/01-first.jpg", $firstMedia->path);
        $this->assertSame('Pic-Time import', $firstMedia->credit);
    }

    public function test_apply_picks_up_files_added_after_initial_import(): void
    {
        Storage::fake('public');

        $story = $this->makePicTimeStory('mountain-engagement');

        $this->seedDirectory($story->slug, ['01-one.jpg', '02-two.jpg']);

        $existing = Media::query()->create([
            'disk' => 'public',
            'path' => "imports/pictime/{$story->slug}/01-one.jpg",
            'filename' => '01-one.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        Artisan::call('pictime:attach-existing', [
            '--slug' => [$story->slug],
            '--apply' => true,
        ]);

        $story->refresh();
        $attached = $story->media()->orderBy('mediables.sort_order')->get();

        $this->assertCount(2, $attached, 'Existing Media row should be reused, new file should get a new Media row.');
        $this->assertSame($existing->id, $attached->first()->id, 'First existing row should be reused.');
        $this->assertNotEquals($existing->id, $attached->last()->id);
        $this->assertSame('02-two.jpg', $attached->last()->filename);
    }

    public function test_apply_is_idempotent(): void
    {
        Storage::fake('public');

        $story = $this->makePicTimeStory('lakeside-wedding');

        $this->seedDirectory($story->slug, ['01-a.jpg', '02-b.jpg']);

        Artisan::call('pictime:attach-existing', [
            '--slug' => [$story->slug],
            '--apply' => true,
        ]);

        $firstMediaIds = $story->fresh()->media()->pluck('media.id')->all();

        Artisan::call('pictime:attach-existing', [
            '--slug' => [$story->slug],
            '--apply' => true,
        ]);

        $secondMediaIds = $story->fresh()->media()->pluck('media.id')->all();

        $this->assertSame($firstMediaIds, $secondMediaIds, 'Re-running should not duplicate Media rows or pivot rows.');
        $this->assertSame(2, Media::query()->count());
    }

    public function test_existing_hero_is_preserved_unless_reset_hero_passed(): void
    {
        Storage::fake('public');

        $story = $this->makePicTimeStory('sunrise-ceremony');

        $this->seedDirectory($story->slug, ['01-first.jpg', '02-second.jpg']);

        $curatedHero = Media::query()->create([
            'disk' => 'public',
            'path' => 'curated/hero.jpg',
            'filename' => 'hero.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $story->forceFill(['hero_media_id' => $curatedHero->id])->save();

        Artisan::call('pictime:attach-existing', [
            '--slug' => [$story->slug],
            '--apply' => true,
        ]);

        $this->assertSame(
            $curatedHero->id,
            $story->fresh()->hero_media_id,
            'Curated hero must not be overwritten without --reset-hero.'
        );

        Artisan::call('pictime:attach-existing', [
            '--slug' => [$story->slug],
            '--apply' => true,
            '--reset-hero' => true,
        ]);

        $story->refresh();
        $this->assertNotSame($curatedHero->id, $story->hero_media_id);
        $this->assertSame('01-first.jpg', $story->heroMedia?->filename);
    }

    public function test_journal_posts_and_wedding_stories_are_both_processed(): void
    {
        Storage::fake('public');

        $post = JournalPost::query()->create([
            'title' => 'Bridal Tips',
            'slug' => 'bridal-tips',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'canonical_url' => 'https://donaldsexton.pic-time.com/bridal-tips',
        ]);

        $story = $this->makePicTimeStory('garden-wedding');

        $this->seedDirectory($post->slug, ['01-a.jpg']);
        $this->seedDirectory($story->slug, ['01-b.jpg']);

        $exitCode = Artisan::call('pictime:attach-existing', [
            '--apply' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertSame(1, $post->fresh()->media()->count());
        $this->assertSame(1, $story->fresh()->media()->count());
    }

    public function test_warns_when_no_records_match(): void
    {
        Storage::fake('public');

        $exitCode = Artisan::call('pictime:attach-existing', [
            '--slug' => ['nonexistent-slug'],
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('No matching Pic-Time records', Artisan::output());
    }

    private function makePicTimeStory(string $slug): WeddingStory
    {
        return WeddingStory::query()->create([
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'published',
            'story_type' => 'wedding',
            'published_at' => now()->subDay(),
            'canonical_url' => "https://donaldsexton.pic-time.com/{$slug}",
        ]);
    }

    private function seedDirectory(string $slug, array $filenames): void
    {
        $disk = Storage::disk('public');

        foreach ($filenames as $filename) {
            $disk->put("imports/pictime/{$slug}/{$filename}", $this->jpegBytes());
        }
    }

    private function jpegBytes(): string
    {
        $image = imagecreatetruecolor(8, 8);

        if (! $image instanceof \GdImage) {
            throw new \RuntimeException('Test image could not be created.');
        }

        try {
            ob_start();
            imagejpeg($image, null, 70);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }
    }
}

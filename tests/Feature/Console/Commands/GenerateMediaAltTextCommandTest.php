<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateMediaAltTextCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.key', 'test-key');
        config()->set('services.anthropic.version', '2023-06-01');

        Storage::fake('public');
    }

    private function fakeFile(string $path): void
    {
        Storage::disk('public')->put($path, str_repeat('x', 100));
    }

    public function test_fills_empty_alt_text_for_each_media(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->fakeToolPayload('Bride and groom walking through Clearwater garden venue'))
                ->push($this->fakeToolPayload('Wedding party portraits at sunset on Tampa Bay dock')),
        ]);

        $this->fakeFile('media/first.jpg');
        $this->fakeFile('media/second.jpg');

        $first = Media::create(['path' => 'media/first.jpg', 'filename' => 'first.jpg', 'disk' => 'public']);
        $second = Media::create(['path' => 'media/second.jpg', 'filename' => 'second.jpg', 'disk' => 'public']);

        $this->artisan('media:generate-alt-text', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame('Bride and groom walking through Clearwater garden venue', $first->fresh()->alt_text);
        $this->assertSame('Wedding party portraits at sunset on Tampa Bay dock', $second->fresh()->alt_text);
    }

    public function test_skips_media_that_already_have_alt_text_unless_all_flag(): void
    {
        $this->fakeFile('media/filled.jpg');
        $this->fakeFile('media/empty.jpg');

        $alreadyFilled = Media::create([
            'path' => 'media/filled.jpg',
            'filename' => 'filled.jpg',
            'disk' => 'public',
            'alt_text' => 'Existing alt text we keep',
        ]);

        $needsAlt = Media::create(['path' => 'media/empty.jpg', 'filename' => 'empty.jpg', 'disk' => 'public']);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('New generated alt text for empty image')),
        ]);

        $this->artisan('media:generate-alt-text', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame('Existing alt text we keep', $alreadyFilled->fresh()->alt_text);
        $this->assertSame('New generated alt text for empty image', $needsAlt->fresh()->alt_text);
        Http::assertSentCount(1);
    }

    public function test_all_flag_regenerates_images_with_existing_alt_text(): void
    {
        $this->fakeFile('media/existing.jpg');

        $media = Media::create([
            'path' => 'media/existing.jpg',
            'filename' => 'existing.jpg',
            'disk' => 'public',
            'alt_text' => 'Old alt text',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Refreshed alt text describing the image')),
        ]);

        $this->artisan('media:generate-alt-text', ['--all' => true, '--sleep' => 0])->assertSuccessful();

        $this->assertSame('Refreshed alt text describing the image', $media->fresh()->alt_text);
    }

    public function test_dry_run_does_not_call_api_or_save(): void
    {
        Http::fake();

        $media = Media::create(['path' => 'media/test.jpg', 'filename' => 'test.jpg', 'disk' => 'public']);

        $this->artisan('media:generate-alt-text', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($media->fresh()->alt_text);
        Http::assertNothingSent();
    }

    public function test_media_option_targets_specific_ids(): void
    {
        $this->fakeFile('media/target.jpg');
        $this->fakeFile('media/other.jpg');

        $target = Media::create(['path' => 'media/target.jpg', 'filename' => 'target.jpg', 'disk' => 'public']);
        $other = Media::create(['path' => 'media/other.jpg', 'filename' => 'other.jpg', 'disk' => 'public']);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Targeted image alt text')),
        ]);

        $this->artisan('media:generate-alt-text', ['--media' => [$target->id], '--sleep' => 0])->assertSuccessful();

        $this->assertSame('Targeted image alt text', $target->fresh()->alt_text);
        $this->assertNull($other->fresh()->alt_text);
        Http::assertSentCount(1);
    }

    public function test_limit_caps_number_of_images_processed(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->fakeFile("media/img-{$i}.jpg");
            Media::create(['path' => "media/img-{$i}.jpg", 'filename' => "img-{$i}.jpg", 'disk' => 'public']);
        }

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Generated alt text for image')),
        ]);

        $this->artisan('media:generate-alt-text', ['--limit' => 2, '--sleep' => 0])->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertSame(2, Media::whereNotNull('alt_text')->where('alt_text', '!=', '')->count());
    }

    public function test_stops_gracefully_when_time_budget_is_exceeded(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->fakeFile("media/budget-{$i}.jpg");
            Media::create(['path' => "media/budget-{$i}.jpg", 'filename' => "budget-{$i}.jpg", 'disk' => 'public']);
        }

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Alt text for one processed image')),
        ]);

        $this->artisan('media:generate-alt-text', ['--max-seconds' => 1, '--sleep' => 1])
            ->expectsOutputToContain('still pending')
            ->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame(1, Media::whereNotNull('alt_text')->where('alt_text', '!=', '')->count());
    }

    public function test_max_seconds_zero_disables_the_budget(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->fakeFile("media/nobudget-{$i}.jpg");
            Media::create(['path' => "media/nobudget-{$i}.jpg", 'filename' => "nobudget-{$i}.jpg", 'disk' => 'public']);
        }

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Alt text generated for image')),
        ]);

        $this->artisan('media:generate-alt-text', ['--max-seconds' => 0, '--sleep' => 0])->assertSuccessful();

        Http::assertSentCount(3);
        $this->assertSame(3, Media::whereNotNull('alt_text')->where('alt_text', '!=', '')->count());
    }

    public function test_skips_media_with_empty_path(): void
    {
        Http::fake();

        Media::create(['path' => '', 'filename' => 'empty.jpg', 'disk' => 'public']);

        $this->artisan('media:generate-alt-text')
            ->expectsOutputToContain('No media records need alt text generation.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_reports_no_work_when_nothing_to_generate(): void
    {
        Media::create([
            'path' => 'media/filled.jpg',
            'filename' => 'filled.jpg',
            'disk' => 'public',
            'alt_text' => 'Already has alt text',
        ]);

        Http::fake();

        $this->artisan('media:generate-alt-text')
            ->expectsOutputToContain('No media records need alt text generation.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_prefers_small_webp_variant_over_full_size_original(): void
    {
        Storage::disk('public')->put('media/photo.jpg', str_repeat('O', 5000));
        Storage::disk('public')->put('media/photo-640.webp', 'SMALL-640-WEBP');
        Storage::disk('public')->put('media/photo-1080.webp', 'BEST-1080-WEBP');

        $media = Media::create(['path' => 'media/photo.jpg', 'filename' => 'photo.jpg', 'disk' => 'public']);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Alt text from the small webp variant')),
        ]);

        $this->artisan('media:generate-alt-text', ['--sleep' => 0])->assertSuccessful();

        Http::assertSent(function ($request): bool {
            $image = collect($request->data()['messages'][0]['content'])
                ->firstWhere('type', 'image')['source'];

            return $image['media_type'] === 'image/webp'
                && $image['data'] === base64_encode('BEST-1080-WEBP');
        });

        $this->assertSame('Alt text from the small webp variant', $media->fresh()->alt_text);
    }

    public function test_falls_back_to_full_size_webp_sibling_then_original(): void
    {
        Storage::disk('public')->put('media/only.jpg', 'ORIGINAL-JPEG-BYTES');

        $media = Media::create(['path' => 'media/only.jpg', 'filename' => 'only.jpg', 'disk' => 'public']);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Alt text from the original jpeg')),
        ]);

        $this->artisan('media:generate-alt-text', ['--sleep' => 0])->assertSuccessful();

        Http::assertSent(function ($request): bool {
            $image = collect($request->data()['messages'][0]['content'])
                ->firstWhere('type', 'image')['source'];

            return $image['media_type'] === 'image/jpeg'
                && $image['data'] === base64_encode('ORIGINAL-JPEG-BYTES');
        });

        $this->assertSame('Alt text from the original jpeg', $media->fresh()->alt_text);
    }

    public function test_skips_oversized_original_without_crashing_the_batch(): void
    {
        Storage::disk('public')->put('media/huge.jpg', str_repeat('H', 15 * 1024 * 1024 + 1));
        Storage::disk('public')->put('media/small.jpg', 'small original bytes');

        $huge = Media::create(['path' => 'media/huge.jpg', 'filename' => 'huge.jpg', 'disk' => 'public']);
        $small = Media::create(['path' => 'media/small.jpg', 'filename' => 'small.jpg', 'disk' => 'public']);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Alt text for the small image')),
        ]);

        $this->artisan('media:generate-alt-text', ['--sleep' => 0])->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertNull($huge->fresh()->alt_text);
        $this->assertSame('Alt text for the small image', $small->fresh()->alt_text);
    }

    public function test_raises_memory_limit_when_below_target(): void
    {
        // PHP refuses to set memory_limit below current usage, so pick a
        // baseline above real usage yet under the command's 256M target.
        $baselineMb = (int) ceil(memory_get_usage(true) / 1048576) + 32;

        if ($baselineMb >= 256) {
            $this->markTestSkipped('Process already near 256M; cannot exercise the raise path.');
        }

        $original = ini_get('memory_limit');

        if (@ini_set('memory_limit', $baselineMb.'M') === false) {
            ini_set('memory_limit', $original);
            $this->markTestSkipped('Could not lower memory_limit to exercise the raise path.');
        }

        try {
            Media::create(['path' => 'media/x.jpg', 'filename' => 'x.jpg', 'disk' => 'public']);

            Http::fake();

            $this->artisan('media:generate-alt-text', ['--dry-run' => true])->assertSuccessful();

            $this->assertSame('256M', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $original);
        }
    }

    public function test_does_not_lower_an_unlimited_memory_limit(): void
    {
        $original = ini_get('memory_limit');
        ini_set('memory_limit', '-1');

        try {
            Media::create(['path' => 'media/y.jpg', 'filename' => 'y.jpg', 'disk' => 'public']);

            Http::fake();

            $this->artisan('media:generate-alt-text', ['--dry-run' => true])->assertSuccessful();

            $this->assertSame('-1', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $original);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeToolPayload(string $altText): array
    {
        return [
            'content' => [[
                'type' => 'tool_use',
                'name' => 'write_alt_text',
                'input' => [
                    'alt_text' => $altText,
                ],
            ]],
        ];
    }
}

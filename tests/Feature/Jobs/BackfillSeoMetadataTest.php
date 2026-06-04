<?php

namespace Tests\Feature\Jobs;

use App\Jobs\BackfillSeoMetadata;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackfillSeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.key', 'test-key');
        config()->set('services.anthropic.model', 'claude-haiku-4-5-20251001');
        config()->set('services.anthropic.version', '2023-06-01');
    }

    public function test_fills_blank_seo_fields_for_a_story(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->toolPayload(
                'write_wedding_story_seo',
                'Generated Story Title',
                'A generated description that is long enough to read like a real meta description from the model.',
            )),
        ]);

        $story = WeddingStory::create([
            'title' => 'A Wedding',
            'slug' => 'a-wedding',
            'status' => 'published',
        ]);

        dispatch_sync(new BackfillSeoMetadata($story));

        $fresh = $story->fresh();
        $this->assertSame('Generated Story Title', $fresh->seo_title);
        $this->assertStringContainsString('generated description', $fresh->seo_description);
    }

    public function test_preserves_existing_fields_and_only_fills_the_blank_one(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->toolPayload(
                'write_wedding_story_seo',
                'Should Not Be Used',
                'A generated description that should fill the empty description slot only.',
            )),
        ]);

        $story = WeddingStory::create([
            'title' => 'A Wedding',
            'slug' => 'a-wedding',
            'status' => 'published',
            'seo_title' => 'Hand written title we keep',
        ]);

        dispatch_sync(new BackfillSeoMetadata($story));

        $fresh = $story->fresh();
        $this->assertSame('Hand written title we keep', $fresh->seo_title);
        $this->assertStringContainsString('empty description slot', $fresh->seo_description);
    }

    public function test_no_ops_and_never_calls_api_when_both_fields_present(): void
    {
        Http::fake();

        $story = WeddingStory::create([
            'title' => 'A Wedding',
            'slug' => 'a-wedding',
            'status' => 'published',
            'seo_title' => 'Title',
            'seo_description' => 'Description.',
        ]);

        dispatch_sync(new BackfillSeoMetadata($story));

        Http::assertNothingSent();
    }

    public function test_routes_a_venue_to_the_venue_generator(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response($this->toolPayload(
                'write_venue_seo',
                'Venue SEO Title',
                'A generated venue description that reads like a real meta description for the guide page.',
            )),
        ]);

        $venue = Venue::factory()->create(['seo_title' => null, 'seo_description' => null]);

        dispatch_sync(new BackfillSeoMetadata($venue));

        $this->assertSame('Venue SEO Title', $venue->fresh()->seo_title);
    }

    /**
     * @return array<string, mixed>
     */
    private function toolPayload(string $tool, string $title, string $description): array
    {
        return [
            'content' => [[
                'type' => 'tool_use',
                'name' => $tool,
                'input' => ['title' => $title, 'description' => $description],
            ]],
        ];
    }
}

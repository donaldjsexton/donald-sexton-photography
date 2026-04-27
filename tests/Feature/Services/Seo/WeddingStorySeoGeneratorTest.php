<?php

namespace Tests\Feature\Services\Seo;

use App\Models\WeddingStory;
use App\Services\Seo\WeddingStorySeoGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeddingStorySeoGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.key', 'test-key');
        config()->set('services.anthropic.model', 'claude-haiku-4-5-20251001');
        config()->set('services.anthropic.version', '2023-06-01');
    }

    public function test_returns_null_when_api_key_missing(): void
    {
        config()->set('services.anthropic.key', '');

        $story = WeddingStory::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
        ]);

        $this->assertNull((new WeddingStorySeoGenerator)->generate($story));
    }

    public function test_parses_tool_use_response_into_generated_seo(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_wedding_story_seo',
                    'input' => [
                        'title' => 'Stephanie & Scott at Powel Crosley Estate',
                        'description' => 'A waterfront Sarasota wedding for Stephanie and Scott at the Powel Crosley Estate, captured in soft afternoon light.',
                    ],
                ]],
            ], 200),
        ]);

        $story = WeddingStory::create([
            'title' => 'Stephanie & Scott — Powel Crosley Estate',
            'slug' => 'stephanie-scott-powel-crosley',
            'status' => 'published',
            'location_name' => 'Powel Crosley Estate',
            'city' => 'Sarasota',
            'state' => 'FL',
            'client_names' => ['Stephanie', 'Scott'],
            'excerpt' => 'A waterfront wedding at the historic Powel Crosley Estate.',
        ]);

        $result = (new WeddingStorySeoGenerator)->generate($story);

        $this->assertNotNull($result);
        $this->assertSame('Stephanie & Scott at Powel Crosley Estate', $result->title);
        $this->assertStringContainsString('Powel Crosley Estate', $result->description);
    }

    public function test_sends_cache_control_and_tool_choice(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_wedding_story_seo',
                    'input' => [
                        'title' => 'A Title',
                        'description' => 'A description that is long enough to satisfy the prompt requirements without exceeding the 160 character ceiling at all here.',
                    ],
                ]],
            ], 200),
        ]);

        $story = WeddingStory::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
        ]);

        (new WeddingStorySeoGenerator)->generate($story);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['system'][0]['cache_control']['type'] ?? null) === 'ephemeral'
                && ($body['tool_choice']['name'] ?? null) === 'write_wedding_story_seo'
                && ($body['model'] ?? null) === 'claude-haiku-4-5-20251001';
        });
    }

    public function test_returns_null_when_response_lacks_tool_use_block(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'I cannot generate this.']],
            ], 200),
        ]);

        $story = WeddingStory::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
        ]);

        $this->assertNull((new WeddingStorySeoGenerator)->generate($story));
    }

    public function test_returns_null_on_non_200_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $story = WeddingStory::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
        ]);

        $this->assertNull((new WeddingStorySeoGenerator)->generate($story));
    }

    public function test_truncates_overlong_title_and_description(): void
    {
        $longTitle = str_repeat('A', 200);
        $longDescription = str_repeat('B', 400);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_wedding_story_seo',
                    'input' => [
                        'title' => $longTitle,
                        'description' => $longDescription,
                    ],
                ]],
            ], 200),
        ]);

        $story = WeddingStory::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
        ]);

        $result = (new WeddingStorySeoGenerator)->generate($story);

        $this->assertNotNull($result);
        $this->assertLessThanOrEqual(WeddingStorySeoGenerator::TITLE_MAX_LENGTH, mb_strlen($result->title));
        $this->assertLessThanOrEqual(WeddingStorySeoGenerator::DESCRIPTION_MAX_LENGTH, mb_strlen($result->description));
    }

    public function test_user_message_includes_story_context(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_wedding_story_seo',
                    'input' => [
                        'title' => 'Title',
                        'description' => 'Description.',
                    ],
                ]],
            ], 200),
        ]);

        $story = WeddingStory::create([
            'title' => 'Cathy & Pete at Sandpearl Resort',
            'slug' => 'cathy-pete-sandpearl',
            'status' => 'published',
            'location_name' => 'Sandpearl Resort',
            'city' => 'Clearwater Beach',
            'state' => 'FL',
            'client_names' => ['Cathy', 'Pete'],
            'excerpt' => 'A beachfront ceremony at sunset.',
            'body' => '<p>Cathy and Pete were married on the sand at <strong>Sandpearl Resort</strong>.</p>',
        ]);

        (new WeddingStorySeoGenerator)->generate($story);

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][0]['content'] ?? '';

            return is_string($content)
                && str_contains($content, 'Cathy & Pete')
                && str_contains($content, 'Sandpearl Resort')
                && str_contains($content, 'Clearwater Beach, FL')
                && str_contains($content, 'A beachfront ceremony at sunset.')
                && ! str_contains($content, '<strong>');
        });
    }
}

<?php

namespace Tests\Feature\Services\Seo;

use App\Models\Venue;
use App\Services\Seo\VenueSeoGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VenueSeoGeneratorTest extends TestCase
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

        $venue = Venue::factory()->create();

        $this->assertNull((new VenueSeoGenerator)->generate($venue));
    }

    public function test_parses_tool_use_response_into_generated_seo(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_venue_seo',
                    'input' => [
                        'title' => 'Powel Crosley Estate Wedding Photographer',
                        'description' => 'A photographer’s guide to weddings at the Powel Crosley Estate in Sarasota, with real weddings shot on the waterfront grounds.',
                    ],
                ]],
            ], 200),
        ]);

        $venue = Venue::factory()->create([
            'name' => 'Powel Crosley Estate',
            'city' => 'Sarasota',
            'state' => 'FL',
            'summary' => 'A historic waterfront estate.',
        ]);

        $result = (new VenueSeoGenerator)->generate($venue);

        $this->assertNotNull($result);
        $this->assertSame('Powel Crosley Estate Wedding Photographer', $result->title);
        $this->assertStringContainsString('Powel Crosley Estate', $result->description);
    }

    public function test_user_message_includes_venue_context_and_uses_tool_choice(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_venue_seo',
                    'input' => ['title' => 'Title', 'description' => 'Description.'],
                ]],
            ], 200),
        ]);

        $venue = Venue::factory()->create([
            'name' => 'Sandpearl Resort',
            'city' => 'Clearwater Beach',
            'state' => 'FL',
            'summary' => 'Beachfront ceremonies at sunset.',
        ]);

        (new VenueSeoGenerator)->generate($venue);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $content = $body['messages'][0]['content'] ?? '';

            return ($body['tool_choice']['name'] ?? null) === 'write_venue_seo'
                && is_string($content)
                && str_contains($content, 'Sandpearl Resort')
                && str_contains($content, 'Clearwater Beach, FL');
        });
    }

    public function test_returns_null_on_non_200_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $this->assertNull((new VenueSeoGenerator)->generate(Venue::factory()->create()));
    }
}

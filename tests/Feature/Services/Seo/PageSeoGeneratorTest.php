<?php

namespace Tests\Feature\Services\Seo;

use App\Models\Page;
use App\Services\Seo\PageSeoGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageSeoGeneratorTest extends TestCase
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

        $page = Page::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
            'template' => 'custom',
        ]);

        $this->assertNull((new PageSeoGenerator)->generate($page));
    }

    public function test_parses_tool_use_response_into_generated_seo(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_page_seo',
                    'input' => [
                        'title' => 'About Donald Sexton, Tampa Bay Wedding Photographer',
                        'description' => 'Meet Donald Sexton, a Clearwater-based wedding photographer covering Tampa Bay. A short look at the work, the approach, and how to get in touch.',
                    ],
                ]],
            ], 200),
        ]);

        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
            'template' => 'about',
            'excerpt' => 'A short bio for Donald Sexton.',
        ]);

        $result = (new PageSeoGenerator)->generate($page);

        $this->assertNotNull($result);
        $this->assertStringContainsString('About Donald Sexton', $result->title);
        $this->assertStringContainsString('Clearwater', $result->description);
    }

    public function test_sends_cache_control_and_tool_choice(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_page_seo',
                    'input' => [
                        'title' => 'A Title',
                        'description' => 'A description that is long enough to satisfy the prompt requirements without exceeding the 160 character ceiling at all here.',
                    ],
                ]],
            ], 200),
        ]);

        $page = Page::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
            'template' => 'custom',
        ]);

        (new PageSeoGenerator)->generate($page);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['system'][0]['cache_control']['type'] ?? null) === 'ephemeral'
                && ($body['tool_choice']['name'] ?? null) === 'write_page_seo'
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

        $page = Page::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
            'template' => 'custom',
        ]);

        $this->assertNull((new PageSeoGenerator)->generate($page));
    }

    public function test_returns_null_on_non_200_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $page = Page::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
            'template' => 'custom',
        ]);

        $this->assertNull((new PageSeoGenerator)->generate($page));
    }

    public function test_truncates_overlong_title_and_description(): void
    {
        $longTitle = str_repeat('A', 200);
        $longDescription = str_repeat('B', 400);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_page_seo',
                    'input' => [
                        'title' => $longTitle,
                        'description' => $longDescription,
                    ],
                ]],
            ], 200),
        ]);

        $page = Page::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
            'template' => 'custom',
        ]);

        $result = (new PageSeoGenerator)->generate($page);

        $this->assertNotNull($result);
        $this->assertLessThanOrEqual(PageSeoGenerator::TITLE_MAX_LENGTH, mb_strlen($result->title));
        $this->assertLessThanOrEqual(PageSeoGenerator::DESCRIPTION_MAX_LENGTH, mb_strlen($result->description));
    }

    public function test_user_message_includes_page_context(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_page_seo',
                    'input' => [
                        'title' => 'Title',
                        'description' => 'Description.',
                    ],
                ]],
            ], 200),
        ]);

        $page = Page::create([
            'title' => 'Frequently Asked Questions',
            'slug' => 'faq',
            'status' => 'published',
            'template' => 'faq',
            'excerpt' => 'Common questions about booking and coverage.',
            'body' => '<p>How far in advance should I book? <strong>About 9-12 months</strong>.</p>',
        ]);

        (new PageSeoGenerator)->generate($page);

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][0]['content'] ?? '';

            return is_string($content)
                && str_contains($content, 'Frequently Asked Questions')
                && str_contains($content, 'faq')
                && str_contains($content, 'Common questions about booking')
                && ! str_contains($content, '<strong>');
        });
    }
}

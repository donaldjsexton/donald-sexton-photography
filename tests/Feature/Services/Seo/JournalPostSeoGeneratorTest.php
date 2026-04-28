<?php

namespace Tests\Feature\Services\Seo;

use App\Models\JournalPost;
use App\Services\Seo\JournalPostSeoGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JournalPostSeoGeneratorTest extends TestCase
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

        $post = JournalPost::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
            'post_type' => 'advice',
        ]);

        $this->assertNull((new JournalPostSeoGenerator)->generate($post));
    }

    public function test_parses_tool_use_response_into_generated_seo(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_journal_post_seo',
                    'input' => [
                        'title' => 'How to Choose Your Wedding Photographer',
                        'description' => 'A practical guide to choosing a Tampa Bay wedding photographer, from style fit to coverage hours and what to ask before you book.',
                    ],
                ]],
            ], 200),
        ]);

        $post = JournalPost::create([
            'title' => 'How to Choose Your Wedding Photographer',
            'slug' => 'how-to-choose-your-wedding-photographer',
            'status' => 'published',
            'post_type' => 'advice',
            'excerpt' => 'A practical guide to picking a wedding photographer.',
        ]);

        $result = (new JournalPostSeoGenerator)->generate($post);

        $this->assertNotNull($result);
        $this->assertSame('How to Choose Your Wedding Photographer', $result->title);
        $this->assertStringContainsString('Tampa Bay', $result->description);
    }

    public function test_sends_cache_control_and_tool_choice(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_journal_post_seo',
                    'input' => [
                        'title' => 'A Title',
                        'description' => 'A description that is long enough to satisfy the prompt requirements without exceeding the 160 character ceiling at all here.',
                    ],
                ]],
            ], 200),
        ]);

        $post = JournalPost::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
            'post_type' => 'advice',
        ]);

        (new JournalPostSeoGenerator)->generate($post);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['system'][0]['cache_control']['type'] ?? null) === 'ephemeral'
                && ($body['tool_choice']['name'] ?? null) === 'write_journal_post_seo'
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

        $post = JournalPost::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
            'post_type' => 'advice',
        ]);

        $this->assertNull((new JournalPostSeoGenerator)->generate($post));
    }

    public function test_returns_null_on_non_200_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $post = JournalPost::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
            'post_type' => 'advice',
        ]);

        $this->assertNull((new JournalPostSeoGenerator)->generate($post));
    }

    public function test_truncates_overlong_title_and_description(): void
    {
        $longTitle = str_repeat('A', 200);
        $longDescription = str_repeat('B', 400);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_journal_post_seo',
                    'input' => [
                        'title' => $longTitle,
                        'description' => $longDescription,
                    ],
                ]],
            ], 200),
        ]);

        $post = JournalPost::create([
            'title' => 'Sample',
            'slug' => 'sample',
            'status' => 'published',
            'post_type' => 'advice',
        ]);

        $result = (new JournalPostSeoGenerator)->generate($post);

        $this->assertNotNull($result);
        $this->assertLessThanOrEqual(JournalPostSeoGenerator::TITLE_MAX_LENGTH, mb_strlen($result->title));
        $this->assertLessThanOrEqual(JournalPostSeoGenerator::DESCRIPTION_MAX_LENGTH, mb_strlen($result->description));
    }

    public function test_user_message_includes_post_context(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'write_journal_post_seo',
                    'input' => [
                        'title' => 'Title',
                        'description' => 'Description.',
                    ],
                ]],
            ], 200),
        ]);

        $post = JournalPost::create([
            'title' => 'A Real Wedding at Sandpearl',
            'slug' => 'real-wedding-sandpearl',
            'status' => 'published',
            'post_type' => 'real_wedding',
            'author_name' => 'Donald Sexton',
            'excerpt' => 'A beachfront ceremony at sunset.',
            'body' => '<p>The couple were married on the sand at <strong>Sandpearl Resort</strong>.</p>',
        ]);

        (new JournalPostSeoGenerator)->generate($post);

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][0]['content'] ?? '';

            return is_string($content)
                && str_contains($content, 'A Real Wedding at Sandpearl')
                && str_contains($content, 'real wedding')
                && str_contains($content, 'Donald Sexton')
                && str_contains($content, 'A beachfront ceremony at sunset.')
                && ! str_contains($content, '<strong>');
        });
    }
}

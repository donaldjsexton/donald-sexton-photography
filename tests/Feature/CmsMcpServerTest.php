<?php

namespace Tests\Feature;

use App\Models\CmsMcpCredential;
use App\Models\Media;
use App\Models\Site;
use App\Models\WeddingStory;
use App\Tenancy\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CmsMcpServerTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_requests_without_a_valid_token_are_rejected(): void
    {
        $this->postJson('/mcp/cms', $this->rpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'test', 'version' => '1'],
        ]), ['Accept' => 'application/json, text/event-stream'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Authentication required.');

        $issued = CmsMcpCredential::issue('reader', [CmsMcpCredential::READ]);

        $this->mcp($issued['token'].'-nope', 'initialize', [])
            ->assertUnauthorized();

        $this->artisan('cms-mcp:revoke', ['credential' => $issued['credential']->id])
            ->assertSuccessful();

        $this->mcp($issued['token'], 'initialize', [])
            ->assertUnauthorized();
    }

    public function test_initialize_uses_the_mcp_protocol_for_a_valid_token(): void
    {
        $issued = CmsMcpCredential::issue('reader', [CmsMcpCredential::READ]);

        $this->mcp($issued['token'], 'initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'test', 'version' => '1'],
        ])
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-06-18')
            ->assertJsonPath('result.serverInfo.name', 'Donald Sexton Photography CMS');
    }

    public function test_a_foreign_origin_is_rejected(): void
    {
        $issued = CmsMcpCredential::issue('reader', [CmsMcpCredential::READ]);

        $this->postJson('/mcp/cms', $this->rpc('initialize', []), [
            'Authorization' => 'Bearer '.$issued['token'],
            'Accept' => 'application/json, text/event-stream',
            'Origin' => 'https://evil.example',
        ])->assertForbidden();
    }

    public function test_draft_tools_do_not_publish_and_readback_keeps_body_alt_order_and_hero(): void
    {
        $draft = CmsMcpCredential::issue('editor', CmsMcpCredential::DEFAULT_ABILITIES);
        $reader = CmsMcpCredential::issue('reader', [CmsMcpCredential::READ]);

        $this->tool($reader['token'], 'create_draft_story', [
            'title' => 'Should not exist',
        ])->assertJsonPath('error.code', -32602);

        $this->assertSame(0, WeddingStory::query()->count());

        $created = $this->tool($draft['token'], 'create_draft_story', [
            'title' => 'Dailani and Aaron',
            'body' => '<p>Full <strong>story</strong> paragraph.</p>',
            'excerpt' => 'Short excerpt.',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'status' => 'published',
        ])->assertJsonPath('result.isError', false);

        $storyId = (int) $created->json('result.structuredContent.story.id');
        $this->assertSame('draft', $created->json('result.structuredContent.story.status'));
        $this->assertNull($created->json('result.structuredContent.story.published_at'));
        $this->assertSame('<p>Full <strong>story</strong> paragraph.</p>', WeddingStory::query()->find($storyId)->body);

        $first = $this->tool($draft['token'], 'upload_media', [
            'filename' => 'shore.jpg',
            'content_base64' => self::PNG,
            'alt_text' => 'First descriptive alt',
            'idempotency_key' => 'upload-key-001',
        ])->assertJsonPath('result.structuredContent.replayed', false);

        $second = $this->tool($draft['token'], 'upload_media', [
            'filename' => 'cake.gif',
            'content_base64' => self::GIF,
            'alt_text' => 'Second descriptive alt',
            'idempotency_key' => 'upload-key-002',
        ]);

        $firstId = (int) $first->json('result.structuredContent.media.id');
        $secondId = (int) $second->json('result.structuredContent.media.id');

        $this->tool($draft['token'], 'update_draft_story', [
            'story_id' => $storyId,
            'body' => '<p>Full <em>saved</em> story.</p>',
            'seo_description' => 'Updated SEO description',
            'gallery' => [
                ['media_id' => $secondId, 'alt_text' => 'Cake on the table'],
                ['media_id' => $firstId, 'alt_text' => 'Couple at the shoreline'],
            ],
            'hero_media_id' => $firstId,
            'status' => 'published',
        ])->assertJsonPath('result.structuredContent.story.status', 'draft');

        $read = $this->tool($reader['token'], 'lookup_story', [
            'story_id' => $storyId,
        ])->assertJsonPath('result.structuredContent.story.body', '<p>Full <em>saved</em> story.</p>')
            ->assertJsonPath('result.structuredContent.story.seo_description', 'Updated SEO description')
            ->assertJsonPath('result.structuredContent.story.hero.id', $firstId)
            ->assertJsonPath('result.structuredContent.story.hero.alt_text', 'Couple at the shoreline');

        $gallery = $read->json('result.structuredContent.story.gallery');
        $this->assertSame([$secondId, $firstId], array_column($gallery, 'id'));
        $this->assertSame([0, 1], array_column($gallery, 'sort_order'));
        $this->assertSame('Cake on the table', $gallery[0]['alt_text']);
        $this->assertSame('draft', WeddingStory::query()->find($storyId)->status);

        $this->tool($draft['token'], 'publish_story', [
            'story_id' => $storyId,
        ])->assertJsonPath('error.code', -32602);

        $this->assertSame('draft', WeddingStory::query()->find($storyId)->status);
    }

    public function test_upload_retries_do_not_create_a_second_media_row(): void
    {
        $issued = CmsMcpCredential::issue('uploader', [CmsMcpCredential::UPLOAD]);

        $first = $this->tool($issued['token'], 'upload_media', [
            'content_base64' => self::PNG,
            'alt_text' => 'Original alt',
            'idempotency_key' => 'retry-key-001',
        ]);
        $mediaId = (int) $first->json('result.structuredContent.media.id');

        $this->tool($issued['token'], 'upload_media', [
            'content_base64' => self::PNG,
            'alt_text' => 'Replacement alt that must not stick',
            'idempotency_key' => 'retry-key-001',
        ])->assertJsonPath('result.structuredContent.replayed', true)
            ->assertJsonPath('result.structuredContent.media.id', $mediaId);

        $this->assertSame(1, Media::query()->count());
        $this->assertSame('Original alt', Media::query()->find($mediaId)->alt_text);
        $this->assertSame(0, WeddingStory::query()->count());

        $this->tool($issued['token'], 'upload_media', [
            'content_base64' => self::GIF,
            'idempotency_key' => 'retry-key-001',
        ])->assertJsonPath('result.isError', true);

        $this->assertSame(1, Media::query()->count());
    }

    public function test_upload_rejects_non_images_and_oversized_payloads(): void
    {
        config()->set('cms-mcp.max_upload_bytes', 8);

        $issued = CmsMcpCredential::issue('uploader', [CmsMcpCredential::UPLOAD]);

        $this->tool($issued['token'], 'upload_media', [
            'content_base64' => base64_encode('not-an-image'),
            'idempotency_key' => 'bad-type-0001',
        ])->assertJsonPath('result.isError', true);

        $this->tool($issued['token'], 'upload_media', [
            'content_base64' => self::PNG,
            'idempotency_key' => 'too-large-001',
        ])->assertJsonPath('result.isError', true);

        $this->assertSame(0, Media::query()->count());
    }

    public function test_publish_is_explicit_and_does_not_rewrite_the_saved_story(): void
    {
        $publisher = CmsMcpCredential::issue('publisher', [CmsMcpCredential::PUBLISH, CmsMcpCredential::READ]);
        $story = WeddingStory::query()->create([
            'title' => 'Ready story',
            'slug' => 'ready-story',
            'status' => 'draft',
            'body' => '<p>Keep this body.</p>',
            'excerpt' => 'Keep this excerpt.',
        ]);

        $this->tool($publisher['token'], 'update_draft_story', [
            'story_id' => $story->id,
            'body' => '<p>Should not save.</p>',
        ])->assertJsonPath('error.code', -32602);

        $published = $this->tool($publisher['token'], 'publish_story', [
            'story_id' => $story->id,
        ])->assertJsonPath('result.structuredContent.story.status', 'published');

        $story->refresh();
        $this->assertSame('<p>Keep this body.</p>', $story->body);
        $this->assertSame('published', $story->status);
        $this->assertNotNull($story->published_at);

        $this->tool($publisher['token'], 'publish_story', [
            'story_id' => $story->id,
        ])->assertJsonPath('result.structuredContent.story.body', '<p>Keep this body.</p>');

        $this->assertSame(
            $published->json('result.structuredContent.story.published_at'),
            $story->fresh()->published_at?->toIso8601String(),
        );

        $live = WeddingStory::query()->create([
            'title' => 'Already public',
            'slug' => 'already-public',
            'status' => 'published',
            'body' => '<p>Live body.</p>',
            'published_at' => now(),
        ]);

        $editor = CmsMcpCredential::issue('editor', [CmsMcpCredential::DRAFT]);

        $this->tool($editor['token'], 'update_draft_story', [
            'story_id' => $live->id,
            'body' => '<p>Changed live body.</p>',
        ])->assertJsonPath('result.isError', true);

        $this->assertSame('<p>Live body.</p>', $live->fresh()->body);
    }

    public function test_expired_tokens_and_other_sites_cannot_read_stories(): void
    {
        $expired = CmsMcpCredential::issue('expired', [CmsMcpCredential::READ], now()->subMinute());

        $this->mcp($expired['token'], 'tools/list')->assertUnauthorized();

        $secret = 'SITE-A-SECRET-BODY';
        $siteA = Site::query()->where('is_default', true)->first()
            ?? Site::factory()->default()->create();
        $siteB = Site::factory()->create([
            'primary_domain' => 'cms-mcp-other.test',
            'is_default' => false,
            'status' => 'active',
        ]);
        app(CurrentSite::class)->set($siteA);
        WeddingStory::query()->create([
            'title' => 'Site A story',
            'slug' => 'site-a-story',
            'status' => 'draft',
            'body' => $secret,
        ]);
        $siteAToken = CmsMcpCredential::issue('site-a', [CmsMcpCredential::READ]);

        $response = $this->postJson('http://cms-mcp-other.test/mcp/cms', $this->rpc('tools/call', [
            'name' => 'lookup_story',
            'arguments' => ['slug' => 'site-a-story'],
        ]), [
            'Authorization' => 'Bearer '.$siteAToken['token'],
            'Accept' => 'application/json, text/event-stream',
        ]);

        $response->assertUnauthorized();
        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertNotSame($siteA->id, $siteB->id);
    }

    public function test_protocol_framing_auth_and_publish_visibility_rules(): void
    {
        $reader = CmsMcpCredential::issue('reader', [CmsMcpCredential::READ]);
        $headers = [
            'Authorization' => 'Bearer '.$reader['token'],
            'Accept' => 'application/json, text/event-stream',
        ];

        $names = $this->mcp($reader['token'], 'tools/list')
            ->assertOk()
            ->json('result.tools');
        $this->assertSame(['lookup_story'], array_column($names, 'name'));

        $this->postJson('/mcp/cms', [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], $headers)->assertStatus(202);

        $this->call('POST', '/mcp/cms', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_AUTHORIZATION' => 'Bearer '.$reader['token'],
        ], '{')->assertOk()->assertJsonPath('error.code', -32700);

        $this->mcp($reader['token'], 'unknown/method')->assertOk()->assertJsonPath('error.code', -32601);

        $this->postJson('/mcp/cms', $this->rpc('ping'), [
            'Authorization' => 'Bearer '.$reader['token'],
            'Accept' => 'application/json',
        ])->assertStatus(406);

        $this->call('POST', '/mcp/cms', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_AUTHORIZATION' => 'Bearer '.$reader['token'],
        ], '{}')->assertStatus(415);

        $this->postJson('/mcp/cms', $this->rpc('ping'), [
            ...$headers,
            'MCP-Protocol-Version' => '1999-01-01',
        ])->assertStatus(400);

        $this->get('/mcp/cms', ['Accept' => 'text/event-stream'])->assertStatus(405);
        $this->delete('/mcp/cms')->assertStatus(405);

        $editor = CmsMcpCredential::issue('editor', [CmsMcpCredential::DRAFT]);
        $archived = WeddingStory::query()->create([
            'title' => 'Archived',
            'slug' => 'archived-story',
            'status' => 'archived',
            'body' => '<p>Archived body.</p>',
        ]);
        $this->tool($editor['token'], 'update_draft_story', [
            'story_id' => $archived->id,
            'body' => '<p>Replaced.</p>',
        ])->assertJsonPath('result.isError', true);
        $this->assertSame('<p>Archived body.</p>', $archived->fresh()->body);

        $publisher = CmsMcpCredential::issue('publisher', [CmsMcpCredential::PUBLISH]);
        $scheduled = WeddingStory::query()->create([
            'title' => 'Scheduled',
            'slug' => 'scheduled-story',
            'status' => 'draft',
            'body' => '<p>Scheduled body.</p>',
            'published_at' => now()->addWeek(),
        ]);
        $this->tool($publisher['token'], 'publish_story', [
            'story_id' => $scheduled->id,
        ])->assertJsonPath('result.structuredContent.story.status', 'published');
        $scheduled->refresh();
        $this->assertTrue($scheduled->published_at->lessThanOrEqualTo(now()));
        $this->assertSame('<p>Scheduled body.</p>', $scheduled->body);

        $published = WeddingStory::query()->create([
            'title' => 'Public gallery',
            'slug' => 'public-gallery',
            'status' => 'published',
            'body' => '<p>Public body.</p>',
            'published_at' => now(),
        ]);
        $this->tool($editor['token'], 'update_draft_story', [
            'story_id' => $published->id,
            'gallery' => [],
            'hero_media_id' => null,
            'body' => '<p>Changed.</p>',
        ])->assertJsonPath('result.isError', true);
        $this->assertSame('<p>Public body.</p>', $published->fresh()->body);
    }

    public function test_whitespace_in_base64_retries_the_same_image(): void
    {
        $issued = CmsMcpCredential::issue('uploader', [CmsMcpCredential::UPLOAD]);
        $wrapped = chunk_split(self::PNG, 20, "\n");

        $first = $this->tool($issued['token'], 'upload_media', [
            'content_base64' => self::PNG,
            'idempotency_key' => 'wrap-key-0001',
            'alt_text' => 'Wrapped alt',
        ]);
        $second = $this->tool($issued['token'], 'upload_media', [
            'content_base64' => $wrapped,
            'idempotency_key' => 'wrap-key-0001',
            'alt_text' => 'Other alt',
        ]);

        $this->assertSame(
            $first->json('result.structuredContent.media.id'),
            $second->json('result.structuredContent.media.id'),
        );
        $this->assertTrue($second->json('result.structuredContent.replayed'));
        $this->assertSame(1, Media::query()->count());

        config()->set('cms-mcp.max_upload_bytes', 8);
        $this->tool($issued['token'], 'upload_media', [
            'content_base64' => str_repeat('A', 80),
            'idempotency_key' => 'huge-key-0001',
        ])->assertJsonPath('result.isError', true);
        $this->assertSame(1, Media::query()->count());
    }

    public function test_issue_command_stores_only_a_hash_and_omits_publish_by_default(): void
    {
        Artisan::call('cms-mcp:issue', ['name' => 'Grok Bot']);
        $output = Artisan::output();

        $this->assertSame(1, preg_match('/dsp_mcp_[A-Za-z0-9]+/', $output, $matches));

        $credential = CmsMcpCredential::query()->first();
        $this->assertNotNull($credential);
        $this->assertSame(hash('sha256', $matches[0]), $credential->token_hash);
        $this->assertNotSame($matches[0], $credential->token_hash);
        $this->assertSame(CmsMcpCredential::DEFAULT_ABILITIES, $credential->abilities);
        $this->assertNotContains(CmsMcpCredential::PUBLISH, $credential->abilities);
        $this->assertStringNotContainsString($matches[0], (string) json_encode($credential->only(['name', 'token_hash', 'abilities'])));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function mcp(string $token, string $method, array $params = []): TestResponse
    {
        return $this->postJson('/mcp/cms', $this->rpc($method, $params), [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json, text/event-stream',
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function tool(string $token, string $name, array $arguments): TestResponse
    {
        return $this->mcp($token, 'tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function rpc(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params === [] ? (object) [] : $params,
        ];
    }
}

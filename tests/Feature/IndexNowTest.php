<?php

namespace Tests\Feature;

use App\Models\JournalPost;
use App\Models\SiteSetting;
use App\Models\WeddingStory;
use App\Services\IndexNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_route_returns_key_when_configured(): void
    {
        $key = str_repeat('a1b2c3d4', 4);

        SiteSetting::create([
            'indexnow_key' => $key,
        ]);

        $this->get('/'.$key.'.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee($key);
    }

    public function test_key_route_404s_for_wrong_key(): void
    {
        $key = str_repeat('a1b2c3d4', 4);

        SiteSetting::create([
            'indexnow_key' => $key,
        ]);

        $this->get('/'.str_repeat('ffffffff', 4).'.txt')
            ->assertNotFound();
    }

    public function test_key_route_404s_when_unconfigured(): void
    {
        $this->get('/'.str_repeat('a1b2c3d4', 4).'.txt')
            ->assertNotFound();
    }

    public function test_submit_posts_to_indexnow_with_configured_key(): void
    {
        $key = str_repeat('a1b2c3d4', 4);

        SiteSetting::create([
            'indexnow_key' => $key,
        ]);

        config(['app.url' => 'https://example.test']);

        Http::fake([
            'api.indexnow.org/*' => Http::response('', 200),
        ]);

        $result = app(IndexNow::class)->submit(['https://example.test/journal/foo']);

        $this->assertTrue($result);

        Http::assertSent(function ($request) use ($key) {
            $body = $request->data();

            return $request->url() === 'https://api.indexnow.org/IndexNow'
                && $body['host'] === 'example.test'
                && $body['key'] === $key
                && $body['keyLocation'] === 'https://example.test/'.$key.'.txt'
                && $body['urlList'] === ['https://example.test/journal/foo'];
        });
    }

    public function test_submit_no_ops_without_key(): void
    {
        Http::fake();

        $this->assertFalse(app(IndexNow::class)->submit(['https://example.test/x']));

        Http::assertNothingSent();
    }

    public function test_publishing_a_journal_post_pings_indexnow(): void
    {
        SiteSetting::create([
            'indexnow_key' => str_repeat('a1b2c3d4', 4),
        ]);

        $this->mock(IndexNow::class, function (MockInterface $mock): void {
            $mock->shouldReceive('submit')
                ->once()
                ->withArgs(fn (array $urls) => count($urls) === 1
                    && str_ends_with($urls[0], '/journal/spring-notes'));
        });

        JournalPost::create([
            'title' => 'Spring Notes',
            'slug' => 'spring-notes',
            'status' => 'published',
            'post_type' => 'advice',
            'excerpt' => 'A short excerpt.',
            'body' => '<p>Hi.</p>',
            'published_at' => now()->subHour(),
        ]);
    }

    public function test_draft_save_does_not_ping_indexnow(): void
    {
        SiteSetting::create([
            'indexnow_key' => str_repeat('a1b2c3d4', 4),
        ]);

        $this->mock(IndexNow::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('submit');
        });

        WeddingStory::create([
            'title' => 'Draft Story',
            'slug' => 'draft-story',
            'status' => 'draft',
            'event_date' => '2027-05-01',
        ]);
    }
}

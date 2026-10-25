<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPageCachingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function publicPaths(): array
    {
        return [
            'home' => ['/'],
            'collections' => ['/collections'],
            'weddings index' => ['/weddings'],
            'journal index' => ['/journal'],
            'venues index' => ['/venues'],
            'privacy policy' => ['/privacy-policy'],
            'terms of service' => ['/terms-of-service'],
        ];
    }

    #[DataProvider('publicPaths')]
    public function test_public_pages_are_cookie_free_and_edge_cacheable(string $path): void
    {
        $response = $this->get($path)->assertOk();

        $this->assertSame([], $response->headers->getCookies(), "{$path} must not start a session or set cookies.");

        $cacheControl = (string) $response->headers->get('Cache-Control');

        foreach (['public', 'max-age=0', 's-maxage=300', 'stale-while-revalidate=86400'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl, "{$path} is missing Cache-Control: {$directive}");
        }
        $this->assertStringNotContainsString('private', $cacheControl);
    }

    public function test_cms_pages_served_by_the_fallback_are_edge_cacheable(): void
    {
        Page::create([
            'title' => 'Mini sessions',
            'slug' => 'mini-sessions',
            'template' => 'default',
            'status' => 'published',
            'body' => '<p>Mini sessions.</p>',
            'published_at' => now(),
        ]);

        $response = $this->get('/mini-sessions')->assertOk();

        $this->assertSame([], $response->headers->getCookies());
        $this->assertStringContainsString('s-maxage=300', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_ttl_can_be_changed_or_switched_off(): void
    {
        config(['seo.public_cache_ttl' => 60]);
        $this->assertStringContainsString('s-maxage=60', (string) $this->get('/')->headers->get('Cache-Control'));

        config(['seo.public_cache_ttl' => 0]);
        $this->assertStringNotContainsString('s-maxage', (string) $this->get('/')->headers->get('Cache-Control'));
    }

    public function test_error_responses_are_never_edge_cacheable(): void
    {
        $response = $this->get('/definitely-not-a-page')->assertNotFound();

        $this->assertStringNotContainsString('s-maxage', (string) $response->headers->get('Cache-Control'));
    }

    public function test_redirects_are_not_marked_cacheable_by_this_middleware(): void
    {
        $response = $this->get('/contact')->assertRedirect('/inquire');

        $this->assertStringNotContainsString('s-maxage', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_inquiry_form_still_gets_a_session_and_is_not_edge_cacheable(): void
    {
        $response = $this->get('/inquire')->assertOk();

        $this->assertNotEmpty($response->headers->getCookies(), 'The form needs its CSRF/session cookies.');
        $this->assertStringNotContainsString('s-maxage', (string) $response->headers->get('Cache-Control'));
    }
}

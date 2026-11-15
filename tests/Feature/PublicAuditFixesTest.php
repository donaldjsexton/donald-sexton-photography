<?php

namespace Tests\Feature;

use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PublicAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_has_exactly_one_h1_using_the_hero_headline(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<h1\b/i', $content));
        $this->assertStringContainsString(
            '<h1 class="home-hero__intro-copy">Calm wedding photos that feel like your real day.</h1>',
            $content,
        );
        $this->assertStringContainsString('<span class="home-hero__wordmark-line">Donald</span>', $content);
    }

    public function test_about_privacy_and_terms_each_keep_a_single_h1(): void
    {
        foreach (['/about', '/privacy-policy', '/terms-of-service'] as $path) {
            $content = $this->get($path)->assertOk()->getContent();

            $this->assertSame(1, preg_match_all('/<h1\b/i', $content), $path.' should have one H1.');
        }
    }

    public function test_public_layout_links_icons_and_a_public_manifest(): void
    {
        $favicon = file_get_contents(public_path('favicon.ico'));

        $this->assertNotFalse($favicon);
        $this->assertGreaterThan(0, strlen($favicon));
        $this->assertSame("\x00\x00\x01\x00", substr($favicon, 0, 4));

        $content = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('rel="icon"', $content);
        $this->assertStringContainsString('favicon.ico', $content);
        $this->assertStringContainsString('rel="apple-touch-icon"', $content);
        $this->assertStringContainsString('href="'.asset('icon-192.png').'"', $content);
        $this->assertStringContainsString('rel="manifest" href="'.asset('manifest.json').'"', $content);
        $this->assertStringNotContainsString('admin-manifest.json', $content);
        $this->assertStringNotContainsString('fonts.bunny.net', $content);
    }

    public function test_self_hosted_fonts_declare_swap_and_metric_overrides(): void
    {
        $css = file_get_contents(resource_path('css/fonts.css'));

        $this->assertNotFalse($css);
        $this->assertStringContainsString('font-display: swap', $css);
        $this->assertStringContainsString('size-adjust:', $css);
        $this->assertStringContainsString("font-family: 'Cormorant Garamond Fallback'", $css);
        $this->assertStringContainsString("font-family: 'Jost Fallback'", $css);
        $this->assertStringNotContainsString('fonts.bunny.net', $css);
        $this->assertFileExists(public_path('fonts/cormorant-garamond-latin-400-normal.woff2'));
        $this->assertFileExists(public_path('fonts/jost-latin-400-normal.woff2'));
    }

    public function test_trailing_slash_urls_redirect_to_the_canonical_path(): void
    {
        // Laravel's HTTP test client trims trailing slashes before the request
        // is dispatched, so these go through the kernel with the raw path.
        $this->get('/')->assertOk();
        $this->get('/?ref=1')->assertOk();

        $about = $this->dispatchRaw('GET', '/about/');
        $this->assertSame(301, $about->getStatusCode());
        $this->assertSame(url('/about'), $about->headers->get('Location'));

        $privacy = $this->dispatchRaw('GET', '/privacy-policy/?ref=1');
        $this->assertSame(301, $privacy->getStatusCode());
        $this->assertSame(url('/privacy-policy?ref=1'), $privacy->headers->get('Location'));

        $terms = $this->dispatchRaw('GET', '/terms-of-service/');
        $this->assertSame(301, $terms->getStatusCode());
        $this->assertSame(url('/terms-of-service'), $terms->headers->get('Location'));

        $journal = $this->dispatchRaw('HEAD', '/journal/');
        $this->assertSame(301, $journal->getStatusCode());
        $this->assertSame(url('/journal'), $journal->headers->get('Location'));

        $root = $this->dispatchRaw('GET', '/');
        $this->assertSame(200, $root->getStatusCode());

        $post = $this->dispatchRaw('POST', '/about/');
        $this->assertNotSame(301, $post->getStatusCode());
    }

    public function test_pages_without_an_image_use_the_shipped_default_share_image(): void
    {
        $path = storage_path('app/public/media/site/journal-share.jpg');

        $this->assertFileExists($path);
        $this->assertGreaterThan(1000, filesize($path));

        $expected = url('/storage/media/site/journal-share.jpg');

        foreach (['/about', '/privacy-policy', '/terms-of-service'] as $page) {
            $this->get($page)
                ->assertOk()
                ->assertSee('<meta property="og:image" content="'.$expected.'">', false)
                ->assertDontSee('wp-content/uploads', false);
        }
    }

    public function test_about_page_prefers_its_own_hero_over_the_default_share_image(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/about-hero.jpg',
            'filename' => 'about-hero.jpg',
            'mime_type' => 'image/jpeg',
            'alt_text' => 'Donald on the beach',
            'width' => 1600,
            'height' => 1067,
        ]);

        Page::create([
            'title' => 'About',
            'slug' => 'about',
            'template' => 'about',
            'status' => 'published',
            'body' => '<p>About body.</p>',
            'hero_media_id' => $hero->id,
            'published_at' => now()->subDay(),
        ]);

        $this->get('/about')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.url($hero->publicUrl()).'">', false)
            ->assertSee('<meta property="og:image:alt" content="Donald on the beach">', false)
            ->assertDontSee('<meta property="og:image" content="'.url('/storage/media/site/journal-share.jpg').'">', false);
    }

    public function test_journal_share_image_uses_the_copied_media_file(): void
    {
        JournalPost::create([
            'title' => 'A Note From the Journal',
            'slug' => 'a-note-from-the-journal',
            'status' => 'published',
            'post_type' => 'journal',
            'body' => '<p><img src="https://donaldsextonphotography.com/wp-content/uploads/2023/11/DSCF7254-Enhanced-NR-copy.jpg" alt="Journal cover"></p>',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/journal')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.url('/storage/media/site/journal-share.jpg').'">', false)
            ->assertDontSee('wp-content/uploads/2023/11/DSCF7254-Enhanced-NR-copy.jpg', false);
    }

    private function dispatchRaw(string $method, string $uri): Response
    {
        return $this->app->make(Kernel::class)->handle(Request::create($uri, $method));
    }
}

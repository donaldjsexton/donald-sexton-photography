<?php

namespace Tests\Unit;

use App\Models\JournalPost;
use Tests\TestCase;

class JournalPostPresentationTest extends TestCase
{
    public function test_sanitized_body_unwraps_legacy_wix_wrapper_divs_and_keeps_media(): void
    {
        $post = new JournalPost([
            'body' => <<<'HTML'
<div class="s_heNoSkinPhoto">
    <figure>
        <img src="https://example.test/one.jpg" alt="">
    </figure>
</div>
<div class="wixui-rich-text">
    <p class="font_8">Intro paragraph.</p>
</div>
HTML,
        ]);

        $sanitized = (string) $post->sanitizedBody();

        $this->assertStringNotContainsString('s_heNoSkinPhoto', $sanitized);
        $this->assertStringNotContainsString('wixui-rich-text', $sanitized);
        $this->assertStringNotContainsString('<div', $sanitized);
        $this->assertStringContainsString('<figure', $sanitized);
        $this->assertStringContainsString('src="https://example.test/one.jpg"', $sanitized);
        $this->assertStringContainsString('Intro paragraph.', $sanitized);
    }

    public function test_sanitized_body_removes_blank_paragraphs_but_keeps_content_paragraphs(): void
    {
        $post = new JournalPost([
            'body' => <<<'HTML'
<p>Lead paragraph with text.</p>
<p>&nbsp;</p>
<p>   </p>
<p><br></p>
<p>Closing paragraph with text.</p>
HTML,
        ]);

        $sanitized = (string) $post->sanitizedBody();

        $this->assertStringContainsString('Lead paragraph with text.', $sanitized);
        $this->assertStringContainsString('Closing paragraph with text.', $sanitized);
        $this->assertStringNotContainsString('&nbsp;', $sanitized);
        $this->assertStringNotContainsString('<br>', $sanitized);
        $this->assertSame(2, substr_count($sanitized, '<p>'));
    }

    public function test_sanitized_body_preserves_paragraphs_that_wrap_images(): void
    {
        $post = new JournalPost([
            'body' => <<<'HTML'
<p><img src="https://example.test/wrapped.jpg" alt=""></p>
<p></p>
HTML,
        ]);

        $sanitized = (string) $post->sanitizedBody();

        $this->assertStringContainsString('src="https://example.test/wrapped.jpg"', $sanitized);
        $this->assertSame(1, substr_count($sanitized, '<p>'));
    }

    public function test_sanitized_body_unwraps_nested_wix_wrappers(): void
    {
        $post = new JournalPost([
            'body' => <<<'HTML'
<div class="s_outer">
    <div class="s_inner">
        <p>Deep paragraph.</p>
    </div>
</div>
HTML,
        ]);

        $sanitized = (string) $post->sanitizedBody();

        $this->assertStringNotContainsString('<div', $sanitized);
        $this->assertStringContainsString('Deep paragraph.', $sanitized);
    }

    public function test_sanitized_body_strips_wix_typography_class_hooks(): void
    {
        $post = new JournalPost([
            'body' => <<<'HTML'
<p class="font_8">First paragraph.</p>
<p class="font_9 color_11">Second paragraph.</p>
<span class="wixGuard">Inline text.</span>
<h2 class="wixui-heading">Heading text.</h2>
HTML,
        ]);

        $sanitized = (string) $post->sanitizedBody();

        $this->assertStringContainsString('First paragraph.', $sanitized);
        $this->assertStringContainsString('Second paragraph.', $sanitized);
        $this->assertStringContainsString('Inline text.', $sanitized);
        $this->assertStringContainsString('Heading text.', $sanitized);
        $this->assertStringNotContainsString('font_8', $sanitized);
        $this->assertStringNotContainsString('font_9', $sanitized);
        $this->assertStringNotContainsString('color_11', $sanitized);
        $this->assertStringNotContainsString('wixGuard', $sanitized);
        $this->assertStringNotContainsString('wixui-heading', $sanitized);
        $this->assertStringContainsString('<p>First paragraph.</p>', $sanitized);
        $this->assertStringContainsString('<h2>Heading text.</h2>', $sanitized);
    }

    public function test_sanitized_body_preserves_non_wix_classes_alongside_wix_classes(): void
    {
        $post = new JournalPost([
            'body' => '<p class="font_8 intro-lede color_11">Mixed class paragraph.</p>',
        ]);

        $sanitized = (string) $post->sanitizedBody();

        $this->assertStringContainsString('Mixed class paragraph.', $sanitized);
        $this->assertStringContainsString('class="intro-lede"', $sanitized);
        $this->assertStringNotContainsString('font_8', $sanitized);
        $this->assertStringNotContainsString('color_11', $sanitized);
    }

    public function test_sanitized_body_resolves_jetpack_cdn_image_to_local_wp_path(): void
    {
        $fixtureDir = public_path('wp-content/uploads/jetpack-fixture');
        $fixturePath = $fixtureDir.'/foo.jpg';

        if (! is_dir($fixtureDir)) {
            mkdir($fixtureDir, 0755, true);
        }

        file_put_contents($fixturePath, 'fake-image-bytes');

        try {
            $post = new JournalPost([
                'body' => '<p><img src="https://i0.wp.com/example.com/wp-content/uploads/jetpack-fixture/foo.jpg?ssl=1" alt=""></p>',
            ]);

            $sanitized = (string) $post->sanitizedBody();

            $this->assertStringContainsString('src="/wp-content/uploads/jetpack-fixture/foo.jpg"', $sanitized);
            $this->assertStringNotContainsString('i0.wp.com', $sanitized);
            $this->assertStringNotContainsString('example.com', $sanitized);
        } finally {
            if (file_exists($fixturePath)) {
                unlink($fixturePath);
            }

            if (is_dir($fixtureDir)) {
                rmdir($fixtureDir);
            }
        }
    }
}

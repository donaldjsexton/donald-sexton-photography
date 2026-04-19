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
}

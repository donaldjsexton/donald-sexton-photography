<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeddingStoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrapped_rich_text_body_renders_later_paragraphs_and_keeps_formatting(): void
    {
        $excerpt = "Dailani and Aaron's gallery brings together a lot of laughter in matching robes.";

        $story = WeddingStory::create([
            'title' => 'Dailani & Aaron | A Beach Wedding',
            'slug' => 'dailani-aaron-beach-wedding',
            'status' => 'published',
            'excerpt' => $excerpt,
            'body' => <<<HTML
<div class="rich-text">
    <p>{$excerpt}</p>
    <p>The ceremony stayed small, beside a wooden cross on the sand.</p>
    <p>They cut a <strong>berry-topped</strong> cake as the light <em>faded</em>.</p>
    <script>alert("xss")</script>
    <p onclick="alert(1)">Guests stayed for the whole evening.</p>
    <a href="javascript:alert(1)">Skip this link</a>
</div>
HTML,
            'published_at' => now(),
        ]);

        $content = $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee($excerpt)
            ->assertSee('The ceremony stayed small, beside a wooden cross on the sand.', false)
            ->assertSee('<strong>berry-topped</strong>', false)
            ->assertSee('<em>faded</em>', false)
            ->assertSee('Guests stayed for the whole evening.', false)
            ->assertSee('Skip this link', false)
            ->assertDontSee('alert("xss")', false)
            ->assertDontSee('onclick=', false)
            ->assertDontSee('javascript:', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<p class="page-hero__copy">'.preg_quote(e($excerpt), '/').'<\/p>/',
            $content
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<div class="detail-shell rich-text">[\s\S]*'.preg_quote(e($excerpt), '/').'/',
            $content
        );
    }

    public function test_plain_text_paragraphs_render_when_the_opening_repeats_the_excerpt(): void
    {
        $excerpt = 'Some weddings are big productions.';

        $story = WeddingStory::create([
            'title' => 'Plain Story',
            'slug' => 'plain-story',
            'status' => 'published',
            'excerpt' => $excerpt,
            'body' => $excerpt."\n\nThey stood in the surf.\n\nA note that 5 < 10 & friends stays escaped.",
            'published_at' => now(),
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee($excerpt, false)
            ->assertSee('<p>They stood in the surf.</p>', false)
            ->assertSee('5 &lt; 10 &amp; friends', false)
            ->assertDontSee('5 < 10 & friends', false);
    }

    public function test_a_longer_opening_paragraph_is_not_discarded_with_the_excerpt(): void
    {
        $excerpt = 'Opening line of the story.';

        $story = WeddingStory::create([
            'title' => 'Long Opening',
            'slug' => 'long-opening',
            'status' => 'published',
            'excerpt' => $excerpt,
            'body' => '<p>'.$excerpt.' The rest of that paragraph continues on the sand.</p><p>Another paragraph follows.</p>',
            'published_at' => now(),
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('The rest of that paragraph continues on the sand.', false)
            ->assertSee('Another paragraph follows.', false);
    }

    public function test_rich_html_paragraphs_and_native_links_stay_separate_and_safe(): void
    {
        $story = WeddingStory::create([
            'title' => 'Formatted Story',
            'slug' => 'formatted-story',
            'status' => 'published',
            'excerpt' => 'A different excerpt.',
            'body' => <<<'HTML'
<p>First paragraph with <strong>emphasis</strong>.</p>
<p>Second paragraph stays separate.</p>
<p>Planning a beach wedding? <a href="https://donaldsextonphotography.com/inquire">Tell me about your date</a>.</p>
<p>Do not follow <a href="javascript:alert(1)">this</a>.</p>
HTML,
            'published_at' => now(),
        ]);

        $content = $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('<p>First paragraph with <strong>emphasis</strong>.</p>', false)
            ->assertSee('<p>Second paragraph stays separate.</p>', false)
            ->assertSee('<a href="https://donaldsextonphotography.com/inquire">Tell me about your date</a>', false)
            ->assertDontSee('javascript:', false)
            ->getContent();

        $this->assertSame(1, preg_match_all('/<div class="detail-shell rich-text">[\s\S]*?<\/div>/', $content, $sections));
        $this->assertGreaterThanOrEqual(4, substr_count($sections[0][0], '<p>'));
    }

    public function test_markdown_link_syntax_is_left_as_text(): void
    {
        $markdown = 'Planning a beach wedding? [Tell me about your date](https://donaldsextonphotography.com/inquire).';

        $story = WeddingStory::create([
            'title' => 'Markdown CTA',
            'slug' => 'markdown-cta',
            'status' => 'published',
            'excerpt' => 'Excerpt.',
            'body' => '<p>First saved paragraph.</p><p>'.$markdown.'</p>',
            'published_at' => now(),
        ]);

        $content = $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('<p>First saved paragraph.</p>', false)
            ->assertSee($markdown, false)
            ->getContent();

        $this->assertMatchesRegularExpression('/<div class="detail-shell rich-text">[\s\S]*'.preg_quote($markdown, '/').'/', $content);
        $this->assertDoesNotMatchRegularExpression(
            '/<div class="detail-shell rich-text">[\s\S]*<a href="https:\/\/donaldsextonphotography.com\/inquire">Tell me about your date<\/a>/',
            $content
        );
    }

    public function test_a_story_with_no_body_keeps_the_excerpt_and_skips_the_reading_section(): void
    {
        $story = WeddingStory::create([
            'title' => 'Excerpt Only',
            'slug' => 'excerpt-only',
            'status' => 'published',
            'excerpt' => 'A short excerpt is the whole public story.',
            'body' => null,
            'published_at' => now(),
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee('A short excerpt is the whole public story.', false)
            ->assertDontSee('detail-shell', false);
    }

    public function test_hero_image_uses_stored_media_alt_and_keeps_gallery_order(): void
    {
        $images = collect(range(1, 15))->map(function (int $index): Media {
            return Media::create([
                'disk' => 'public',
                'path' => 'media/test/dailani-'.$index.'.jpg',
                'filename' => 'dailani-'.$index.'.jpg',
                'mime_type' => 'image/jpeg',
                'alt_text' => 'Descriptive alt '.$index,
                'width' => 1600,
                'height' => 1066,
            ]);
        });

        $hero = $images->get(11);

        $story = WeddingStory::create([
            'title' => 'Dailani & Aaron | A Beach Wedding',
            'slug' => 'hero-alt-story',
            'status' => 'published',
            'excerpt' => 'Excerpt stays in the hero.',
            'hero_media_id' => $hero->id,
            'published_at' => now(),
        ]);

        $story->media()->attach(
            $images->mapWithKeys(fn (Media $media, int $index): array => [
                $media->id => ['role' => 'gallery', 'sort_order' => $index],
            ])->all()
        );

        $content = $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<img[^>]*src="\/storage\/media\/test\/dailani-12\.jpg"[^>]*alt="Descriptive alt 12"[^>]*fetchpriority="high"/',
            $content
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<img[^>]*fetchpriority="high"[^>]*alt="Dailani &amp; Aaron \| A Beach Wedding"/',
            $content
        );

        preg_match_all('/data-lightbox-src="([^"]+)"/', $content, $matches);

        $expected = collect(range(1, 15))
            ->reject(fn (int $index): bool => $index === 12)
            ->map(fn (int $index): string => '/storage/media/test/dailani-'.$index.'.jpg')
            ->values()
            ->all();

        $this->assertSame($expected, $matches[1]);
    }

    public function test_hero_image_falls_back_to_the_story_title_when_media_alt_is_missing(): void
    {
        $hero = Media::create([
            'disk' => 'public',
            'path' => 'media/test/untitled-hero.jpg',
            'filename' => 'untitled-hero.jpg',
            'mime_type' => 'image/jpeg',
            'alt_text' => null,
        ]);

        $story = WeddingStory::create([
            'title' => 'Untitled Hero Wedding',
            'slug' => 'untitled-hero-wedding',
            'status' => 'published',
            'hero_media_id' => $hero->id,
            'published_at' => now(),
        ]);

        $content = $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<img[^>]*alt="Untitled Hero Wedding"[^>]*fetchpriority="high"/',
            $content
        );
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Text colours that PageSpeed flagged for contrast must stay at or above the
 * WCAG AA ratio (4.5:1 for normal text) against the background they sit on.
 */
class CssContrastTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function flaggedTextColours(): array
    {
        return [
            'hero reassurance on the dark hero' => ['.home-hero__reassurance', '#343434'],
            'discover copy on the off-white section' => ['.home-discover__copy', '#f9f7f4'],
            'review author on a white card' => ['.google-reviews__card-author', '#ffffff'],
            'review date on a white card' => ['.google-reviews__card-date', '#ffffff'],
        ];
    }

    #[DataProvider('flaggedTextColours')]
    public function test_text_colour_meets_wcag_aa(string $selector, string $background): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        $this->assertSame(1, preg_match('/'.preg_quote($selector, '/').'\s*\{[^}]*?\bcolor:\s*([^;]+);/', $css, $block), "No colour found for {$selector}.");

        $colour = trim($block[1]);

        if (preg_match('/^var\(--([a-z-]+)\)$/', $colour, $variable) === 1) {
            $this->assertSame(1, preg_match('/--'.$variable[1].':\s*(#[0-9a-fA-F]{3,6})/', $css, $root));
            $colour = $root[1];
        }

        $this->assertGreaterThanOrEqual(4.5, $this->contrast($colour, $background), "{$selector} ({$colour}) on {$background}");
    }

    private function contrast(string $foreground, string $background): float
    {
        $lighter = max($this->luminance($foreground), $this->luminance($background));
        $darker = min($this->luminance($foreground), $this->luminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $channels = array_map(function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}

<?php

namespace Tests\Unit\Support;

use App\Support\MetaTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MetaTitleTest extends TestCase
{
    private const SITE = 'Donald Sexton Photography';

    /**
     * @return array<string, array{?string, string}>
     */
    public static function titleProvider(): array
    {
        return [
            'plain title gains brand suffix' => [
                'Amanda & Gage at Knotted Roots',
                'Amanda & Gage at Knotted Roots | Donald Sexton Photography',
            ],
            'title already carrying full brand is untouched' => [
                'Clearwater Wedding Photographer | Donald Sexton Photography',
                'Clearwater Wedding Photographer | Donald Sexton Photography',
            ],
            'title carrying short brand form is untouched' => [
                'Wedding Portfolio — Tampa Bay | Donald Sexton',
                'Wedding Portfolio — Tampa Bay | Donald Sexton',
            ],
            'brand match is case-insensitive' => [
                'A Note from donald sexton',
                'A Note from donald sexton',
            ],
            'empty title falls back to site name' => [
                '',
                'Donald Sexton Photography',
            ],
            'null title falls back to site name' => [
                null,
                'Donald Sexton Photography',
            ],
            'title equal to site name is not doubled' => [
                'Donald Sexton Photography',
                'Donald Sexton Photography',
            ],
            'surrounding whitespace is trimmed before formatting' => [
                '  Beach Elopement  ',
                'Beach Elopement | Donald Sexton Photography',
            ],
        ];
    }

    #[DataProvider('titleProvider')]
    public function test_format_appends_brand_idempotently(?string $input, string $expected): void
    {
        $this->assertSame($expected, MetaTitle::format($input, self::SITE));
    }

    public function test_blank_site_name_returns_bare_title(): void
    {
        $this->assertSame('Just a Title', MetaTitle::format('Just a Title', ''));
    }
}

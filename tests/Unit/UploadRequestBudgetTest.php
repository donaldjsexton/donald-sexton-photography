<?php

namespace Tests\Unit;

use App\Support\UploadRequestBudget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UploadRequestBudgetTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function iniSizeProvider(): array
    {
        return [
            'megabytes' => ['8M', 8 * 1024 * 1024],
            'lowercase megabytes' => ['64m', 64 * 1024 * 1024],
            'gigabytes' => ['1G', 1024 * 1024 * 1024],
            'kilobytes' => ['512k', 512 * 1024],
            'plain bytes' => ['1048576', 1048576],
            'unlimited zero' => ['0', 0],
            'unlimited negative' => ['-1', 0],
            'empty string' => ['', 0],
            'whitespace' => ['  16M  ', 16 * 1024 * 1024],
            'garbage' => ['not-a-size', 0],
        ];
    }

    #[DataProvider('iniSizeProvider')]
    public function test_it_resolves_ini_size_shorthand_to_bytes(string $value, int $expected): void
    {
        $this->assertSame($expected, UploadRequestBudget::iniSizeToBytes($value));
    }

    public function test_max_request_bytes_reflects_the_current_post_max_size(): void
    {
        $this->assertSame(
            UploadRequestBudget::iniSizeToBytes((string) ini_get('post_max_size')),
            UploadRequestBudget::maxRequestBytes(),
        );
    }
}

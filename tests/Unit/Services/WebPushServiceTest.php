<?php

namespace Tests\Unit\Services;

use App\Services\WebPushService;
use PHPUnit\Framework\TestCase;

class WebPushServiceTest extends TestCase
{
    public function test_normalize_key_returns_null_for_null(): void
    {
        $this->assertNull(WebPushService::normalizeKey(null));
    }

    public function test_normalize_key_returns_null_for_blank(): void
    {
        $this->assertNull(WebPushService::normalizeKey('   '));
    }

    public function test_normalize_key_trims_whitespace_and_newlines(): void
    {
        $this->assertSame('abc', WebPushService::normalizeKey("  abc\n"));
    }

    public function test_normalize_key_strips_wrapping_quotes(): void
    {
        $this->assertSame('abc', WebPushService::normalizeKey('"abc"'));
        $this->assertSame('abc', WebPushService::normalizeKey("'abc'"));
    }

    public function test_normalize_key_converts_standard_base64_to_base64url(): void
    {
        $this->assertSame('a-b_c', WebPushService::normalizeKey('a+b/c'));
    }

    public function test_normalize_key_strips_padding(): void
    {
        $this->assertSame('abc', WebPushService::normalizeKey('abc=='));
    }

    public function test_normalize_key_round_trips_a_real_private_key(): void
    {
        $raw = random_bytes(32);
        $standardBase64 = base64_encode($raw);

        $normalized = WebPushService::normalizeKey("  $standardBase64  \n");

        $this->assertNotNull($normalized);
        $decoded = base64_decode(strtr($normalized, '-_', '+/'), true);
        $this->assertSame($raw, $decoded);
        $this->assertSame(32, strlen($decoded));
    }

    public function test_normalize_key_round_trips_a_real_public_key(): void
    {
        $raw = "\x04".random_bytes(64);
        $standardBase64 = base64_encode($raw);

        $normalized = WebPushService::normalizeKey($standardBase64);

        $this->assertNotNull($normalized);
        $decoded = base64_decode(strtr($normalized, '-_', '+/'), true);
        $this->assertSame($raw, $decoded);
        $this->assertSame(65, strlen($decoded));
    }
}

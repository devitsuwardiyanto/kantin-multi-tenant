<?php

namespace Tests\Unit;

use App\Support\Tokens\OpaqueToken;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OpaqueTokenTest extends TestCase
{
    public function test_issue_produces_distinct_urlsafe_tokens_with_32_byte_hash(): void
    {
        $a = OpaqueToken::issue(32);
        $b = OpaqueToken::issue(32);

        $this->assertNotSame($a['plain'], $b['plain']);
        $this->assertSame(32, strlen($a['hash']));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $a['plain']);
    }

    public function test_hash_is_deterministic_for_same_plain(): void
    {
        $token = OpaqueToken::issue(32);
        $this->assertSame($token['hash'], OpaqueToken::hash($token['plain']));
    }

    public function test_rejects_entropy_below_128_bits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OpaqueToken::issue(8);
    }
}

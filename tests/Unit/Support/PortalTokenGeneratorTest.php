<?php

namespace Tests\Unit\Support;

use App\Support\PortalTokenGenerator;
use Tests\TestCase;

class PortalTokenGeneratorTest extends TestCase
{
    public function test_generates_a_hex_token_of_the_configured_length(): void
    {
        config(['portal.token_length' => 48]);

        $token = PortalTokenGenerator::generate();

        $this->assertSame(96, strlen($token)); // 48 bytes -> 96 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function test_generates_unique_tokens(): void
    {
        $tokens = array_map(fn () => PortalTokenGenerator::generate(), range(1, 20));

        $this->assertCount(20, array_unique($tokens));
    }
}

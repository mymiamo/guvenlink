<?php

declare(strict_types=1);

namespace Tests;

use App\Support\ShortenerPolicy;
use PHPUnit\Framework\TestCase;

final class ShortenerPolicyTest extends TestCase
{
    public function testDetectsRiskyShortener(): void
    {
        self::assertSame('risky', ShortenerPolicy::category('adf.ly'));
        self::assertSame('risky', ShortenerPolicy::category('subdomain.link.tr'));
    }

    public function testDetectsTrustedShortener(): void
    {
        self::assertSame('safe', ShortenerPolicy::category('bit.ly'));
        self::assertSame('safe', ShortenerPolicy::category('preview.tinyurl.com'));
    }

    public function testReturnsNullForRegularHosts(): void
    {
        self::assertNull(ShortenerPolicy::category('example.com'));
    }
}

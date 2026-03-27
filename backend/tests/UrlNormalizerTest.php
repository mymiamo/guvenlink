<?php

declare(strict_types=1);

namespace Tests;

use App\Services\UrlNormalizer;
use PHPUnit\Framework\TestCase;

final class UrlNormalizerTest extends TestCase
{
    public function testNormalizesDomainWithoutScheme(): void
    {
        $normalizer = new UrlNormalizer();
        $result = $normalizer->normalize('Example.COM');

        self::assertSame('domain', $result['type']);
        self::assertSame('example.com', $result['host']);
        self::assertSame('example.com', $result['normalized_value']);
    }

    public function testNormalizesUrlPathAndQuery(): void
    {
        $normalizer = new UrlNormalizer();
        $result = $normalizer->normalize('https://Example.com/path//to?q=1', 'url');

        self::assertSame('url', $result['type']);
        self::assertSame('example.com/path/to?q=1', $result['normalized_value']);
    }
}

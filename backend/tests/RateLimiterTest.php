<?php

declare(strict_types=1);

namespace Tests;

use App\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function testBlocksAfterLimit(): void
    {
        $limiter = new RateLimiter();
        $bucket = 'phpunit';
        $subject = uniqid('subject-', true);

        $first = $limiter->hit($bucket, $subject, 2, 60);
        $second = $limiter->hit($bucket, $subject, 2, 60);
        $third = $limiter->hit($bucket, $subject, 2, 60);

        self::assertTrue($first['allowed']);
        self::assertTrue($second['allowed']);
        self::assertFalse($third['allowed']);
    }
}

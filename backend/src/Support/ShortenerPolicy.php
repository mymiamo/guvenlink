<?php

declare(strict_types=1);

namespace App\Support;

final class ShortenerPolicy
{
    private const RISKY_SHORTENERS = ['aylink.co', 'link.tr', 'tr.link', 'linkperisi.com', 'pnd.tl', 'adf.ly', 'clk.sh'];
    private const SAFE_SHORTENERS = ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'cutt.ly', 'shorturl.at', 'ow.ly', 'rb.gy', 'is.gd'];

    public static function category(string $hostname): ?string
    {
        $host = strtolower(rtrim($hostname, '.'));

        if (self::matches($host, self::RISKY_SHORTENERS)) {
            return 'risky';
        }

        if (self::matches($host, self::SAFE_SHORTENERS)) {
            return 'safe';
        }

        return null;
    }

    private static function matches(string $hostname, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($hostname === $candidate || str_ends_with($hostname, '.' . $candidate)) {
                return true;
            }
        }

        return false;
    }
}

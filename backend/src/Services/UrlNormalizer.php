<?php

declare(strict_types=1);

namespace App\Services;

final class UrlNormalizer
{
    public function normalize(string $value, ?string $forcedType = null): array
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('URL veya alan adi bos olamaz.');
        }

        $hasScheme = preg_match('#^[a-z][a-z0-9+\-.]*://#i', $value) === 1;
        $candidate = $hasScheme ? $value : 'https://' . ltrim($value, '/');
        $parts = parse_url($candidate);

        if ($parts === false || empty($parts['host'])) {
            throw new \InvalidArgumentException('Gecersiz URL veya alan adi.');
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $path = rawurldecode((string) ($parts['path'] ?? '/'));
        $path = $path === '' ? '/' : preg_replace('#/+#', '/', $path);
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . (string) $parts['query'] : '';
        $normalizedUrl = $host . $path . $query;

        $type = $forcedType ?? (($path !== '/' || $query !== '') ? 'url' : 'domain');
        $normalizedValue = $type === 'domain' ? $host : $normalizedUrl;

        return [
            'type' => $type,
            'host' => $host,
            'normalized_url' => $normalizedUrl,
            'match_value' => $value,
            'normalized_value' => $normalizedValue,
            'normalized_hash' => hash('sha256', $normalizedValue),
            'scheme' => strtolower((string) ($parts['scheme'] ?? 'https')),
        ];
    }
}

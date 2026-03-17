<?php

declare(strict_types=1);

namespace App\Services;

final class UrlNormalizer
{
    public function normalize(string $value, ?string $forcedType = null): array
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('URL/domain bos olamaz.');
        }

        $hasScheme = preg_match('#^[a-z][a-z0-9+\-.]*://#i', $value) === 1;
        $candidate = $hasScheme ? $value : 'https://' . ltrim($value, '/');
        $parts = parse_url($candidate);

        if ($parts === false || empty($parts['host'])) {
            throw new \InvalidArgumentException('Gecersiz URL/domain.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : $path;
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $normalizedUrl = $host . $path . $query;

        $type = $forcedType;
        if ($type === null) {
            $type = ($path !== '/' || $query !== '') ? 'url' : 'domain';
        }

        return [
            'type' => $type,
            'host' => $host,
            'normalized_url' => $normalizedUrl,
            'match_value' => $value,
            'normalized_value' => $type === 'domain' ? $host : $normalizedUrl,
            'normalized_hash' => hash('sha256', $type === 'domain' ? $host : $normalizedUrl),
            'scheme' => strtolower($parts['scheme'] ?? 'https'),
        ];
    }
}

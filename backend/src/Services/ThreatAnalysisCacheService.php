<?php

declare(strict_types=1);

namespace App\Services;

final class ThreatAnalysisCacheService
{
    public function remember(string $cacheKey, callable $resolver): array
    {
        $cached = $this->get($cacheKey);
        if ($cached !== null) {
            $cached['cache'] = ['hit' => true, 'ttl' => (int) ANALYSIS_CACHE_TTL];
            return $cached;
        }

        $payload = $resolver();
        if (is_array($payload)) {
            $payload['cache'] = ['hit' => false, 'ttl' => (int) ANALYSIS_CACHE_TTL];
            $this->put($cacheKey, $payload);
        }

        return $payload;
    }

    public function invalidateAll(): void
    {
        foreach (glob(BASE_PATH . '/storage/cache/analysis-*.json') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function get(string $cacheKey): ?array
    {
        $path = $this->path($cacheKey);
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            @unlink($path);
            return null;
        }

        $expiresAt = (int) ($decoded['expires_at'] ?? 0);
        if ($expiresAt < time()) {
            @unlink($path);
            return null;
        }

        return is_array($decoded['payload'] ?? null) ? $decoded['payload'] : null;
    }

    private function put(string $cacheKey, array $payload): void
    {
        $data = [
            'expires_at' => time() + max(30, (int) ANALYSIS_CACHE_TTL),
            'payload' => $payload,
        ];
        file_put_contents($this->path($cacheKey), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function path(string $cacheKey): string
    {
        return BASE_PATH . '/storage/cache/analysis-' . hash('sha256', $cacheKey) . '.json';
    }
}

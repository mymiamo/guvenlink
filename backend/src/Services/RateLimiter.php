<?php

declare(strict_types=1);

namespace App\Services;

final class RateLimiter
{
    public function hit(string $bucket, string $subject, int $maxAttempts, int $windowSeconds): array
    {
        $file = $this->path($bucket, $subject);
        $payload = $this->read($file);
        $now = time();
        $resetAt = (int) ($payload['reset_at'] ?? 0);

        if ($resetAt <= $now) {
            $payload = [
                'count' => 0,
                'reset_at' => $now + $windowSeconds,
            ];
        }

        $payload['count'] = (int) ($payload['count'] ?? 0) + 1;
        $this->write($file, $payload);

        return [
            'allowed' => $payload['count'] <= $maxAttempts,
            'remaining' => max(0, $maxAttempts - $payload['count']),
            'retryAfter' => max(1, ((int) $payload['reset_at']) - $now),
        ];
    }

    private function path(string $bucket, string $subject): string
    {
        return BASE_PATH . '/storage/cache/ratelimit-' . sha1($bucket . '|' . $subject) . '.json';
    }

    private function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function write(string $file, array $payload): void
    {
        file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

final class RemoteServiceGuard
{
    public function isOpen(string $service): bool
    {
        $state = $this->read($service);
        return (int) ($state['open_until'] ?? 0) > time();
    }

    public function state(string $service): array
    {
        $state = $this->read($service);
        return [
            'service' => $service,
            'available' => !$this->isOpen($service),
            'openUntil' => (int) ($state['open_until'] ?? 0),
            'failures' => (int) ($state['failures'] ?? 0),
        ];
    }

    public function success(string $service): void
    {
        $this->write($service, [
            'failures' => 0,
            'open_until' => 0,
        ]);
    }

    public function failure(string $service): void
    {
        $state = $this->read($service);
        $failures = (int) ($state['failures'] ?? 0) + 1;
        $threshold = max(1, (int) (defined('REMOTE_CIRCUIT_BREAKER_THRESHOLD') ? REMOTE_CIRCUIT_BREAKER_THRESHOLD : 3));
        $ttl = max(30, (int) (defined('REMOTE_CIRCUIT_BREAKER_TTL') ? REMOTE_CIRCUIT_BREAKER_TTL : 300));

        $next = [
            'failures' => $failures,
            'open_until' => $failures >= $threshold ? time() + $ttl : 0,
        ];

        $this->write($service, $next);
    }

    private function path(string $service): string
    {
        return BASE_PATH . '/storage/cache/remote-' . preg_replace('/[^a-z0-9_-]+/i', '-', $service) . '.json';
    }

    private function read(string $service): array
    {
        $file = $this->path($service);
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function write(string $service, array $payload): void
    {
        file_put_contents($this->path($service), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

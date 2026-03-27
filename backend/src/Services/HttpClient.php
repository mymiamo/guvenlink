<?php

declare(strict_types=1);

namespace App\Services;

final class HttpClient
{
    public function requestJson(
        string $method,
        string $url,
        array|string|null $body = null,
        array $headers = [],
        ?int $timeout = null,
        int $retries = 0
    ): array {
        $attempt = 0;
        $timeout ??= max(1, (int) (defined('REMOTE_HTTP_TIMEOUT') ? REMOTE_HTTP_TIMEOUT : 8));
        $headers[] = 'Accept: application/json';

        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        do {
            $attempt++;
            $startedAt = microtime(true);
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                    'content' => is_string($body) ? $body : null,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);

            $raw = @file_get_contents($url, false, $context);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $status = $this->statusCode($http_response_header ?? []);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;

            if (is_array($decoded) && $status >= 200 && $status < 500) {
                return [
                    'ok' => true,
                    'status' => $status,
                    'latency_ms' => $latencyMs,
                    'data' => $decoded,
                ];
            }

            if ($status >= 200 && $status < 300 && $raw === '') {
                return [
                    'ok' => true,
                    'status' => $status,
                    'latency_ms' => $latencyMs,
                    'data' => [],
                ];
            }

            $lastError = [
                'ok' => false,
                'status' => $status,
                'latency_ms' => $latencyMs,
                'error' => is_string($raw) ? trim($raw) : 'network_error',
            ];
        } while ($attempt <= $retries);

        return $lastError ?? [
            'ok' => false,
            'status' => 0,
            'latency_ms' => 0,
            'error' => 'network_error',
        ];
    }

    private function statusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}

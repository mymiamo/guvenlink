<?php

declare(strict_types=1);

namespace App\Services;

final class VirusTotalService
{
    private const BASE = 'https://www.virustotal.com/api/v3';

    public function __construct(
        private readonly HttpClient $http = new HttpClient(),
        private readonly RemoteServiceGuard $guard = new RemoteServiceGuard(),
        private readonly string $apiKey = VIRUSTOTAL_API_KEY,
    ) {
    }

    public function enabled(): bool
    {
        return $this->apiKey !== '';
    }

    public function analyzeUrl(string $url): array
    {
        if (!$this->enabled()) {
            return $this->result('disabled', false, 0);
        }

        if ($this->guard->isOpen('virustotal')) {
            return $this->result('degraded', false, 0);
        }

        $urlId = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
        $report = $this->request('GET', '/urls/' . $urlId);
        if (!$report['ok']) {
            $submit = $this->request('POST', '/urls', 'url=' . rawurlencode($url), ['Content-Type: application/x-www-form-urlencoded']);
            if (!$submit['ok']) {
                $this->guard->failure('virustotal');
                return $this->result('degraded', false, (int) ($submit['latency_ms'] ?? 0));
            }

            $analysisId = $submit['data']['data']['id'] ?? null;
            if (!is_string($analysisId)) {
                $this->guard->failure('virustotal');
                return $this->result('degraded', false, (int) ($submit['latency_ms'] ?? 0));
            }

            $report = $this->request('GET', '/analyses/' . $analysisId);
            if (!$report['ok']) {
                $this->guard->failure('virustotal');
                return $this->result('degraded', false, (int) ($report['latency_ms'] ?? 0));
            }
        }

        $this->guard->success('virustotal');
        $stats = $report['data']['data']['attributes']['stats']
            ?? $report['data']['data']['attributes']['last_analysis_stats']
            ?? null;

        if (!is_array($stats)) {
            return $this->result('clean', false, (int) $report['latency_ms']);
        }

        $malicious = (int) ($stats['malicious'] ?? 0);
        $suspicious = (int) ($stats['suspicious'] ?? 0);
        $total = max(1, array_sum(array_map('intval', $stats)));
        $positives = $malicious + $suspicious;

        $verdict = 'safe';
        $score = 0;
        $confidence = 'low';

        if ($malicious >= 5 || $positives >= 8) {
            $verdict = 'malicious';
            $score = min(90, 60 + ($positives * 4));
            $confidence = 'high';
        } elseif ($malicious >= 1 || $positives >= 3) {
            $verdict = 'suspicious';
            $score = min(74, 35 + ($positives * 6));
            $confidence = 'medium';
        }

        return $this->result($verdict === 'safe' ? 'clean' : 'match', $verdict !== 'safe', (int) $report['latency_ms']) + [
            'verdict' => $verdict,
            'score' => $score,
            'confidence' => $confidence,
            'positives' => $positives,
            'total' => $total,
            'permalink' => $report['data']['data']['links']['self'] ?? ('https://www.virustotal.com/gui/url/' . $urlId),
            'label' => "VirusTotal: {$positives}/{$total} motor tehdit işareti verdi.",
        ];
    }

    private function request(string $method, string $path, ?string $body = null, array $extraHeaders = []): array
    {
        return $this->http->requestJson(
            $method,
            self::BASE . $path,
            $body,
            array_merge(['x-apikey: ' . $this->apiKey], $extraHeaders),
            (int) REMOTE_HTTP_TIMEOUT,
            (int) REMOTE_HTTP_RETRIES
        );
    }

    private function result(string $status, bool $matched, int $latencyMs): array
    {
        return [
            'service' => 'virustotal',
            'status' => $status,
            'matched' => $matched,
            'latencyMs' => $latencyMs,
        ];
    }
}

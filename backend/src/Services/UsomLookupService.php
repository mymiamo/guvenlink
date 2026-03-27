<?php

declare(strict_types=1);

namespace App\Services;

final class UsomLookupService
{
    public function __construct(
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
        private readonly HttpClient $http = new HttpClient(),
        private readonly RemoteServiceGuard $guard = new RemoteServiceGuard(),
    ) {
    }

    public function lookup(string $url): array
    {
        if ($this->guard->isOpen('usom')) {
            return $this->result('degraded', false, 0);
        }

        $normalized = $this->normalizer->normalize($url, 'url');
        $hostCandidates = $this->buildHostCandidates($normalized['host']);
        $candidates = array_merge(
            [
                ['query' => $normalized['normalized_url'], 'type' => 'url', 'exact' => $normalized['normalized_url']],
                ['query' => $normalized['host'], 'type' => 'domain', 'exact' => $normalized['host']],
            ],
            array_map(
                static fn (string $host): array => ['query' => $host, 'type' => 'domain', 'exact' => $host],
                $hostCandidates
            )
        );

        foreach ($candidates as $candidate) {
            $payload = $this->search($candidate['query']);
            if (($payload['status'] ?? '') === 'degraded') {
                return $payload;
            }

            foreach ($payload['models'] ?? [] as $row) {
                $rowType = (string) ($row['type'] ?? 'domain');
                $rowUrl = strtolower(trim((string) ($row['url'] ?? '')));
                if ($rowType !== $candidate['type'] || $rowUrl !== $candidate['exact']) {
                    continue;
                }

                $descCode = strtoupper(trim((string) ($row['desc'] ?? '')));
                $connectionCode = strtoupper(trim((string) ($row['connectiontype'] ?? '')));
                return $this->result('match', true, (int) ($payload['latencyMs'] ?? 0)) + [
                    'type' => $rowType,
                    'source' => 'usom',
                    'verdict' => 'malicious',
                    'score' => 100,
                    'confidence' => 'high',
                    'reason' => 'USOM kaydı nedeniyle zararlı olarak işaretlendi.',
                    'updated_at' => $this->normalizeDate((string) ($row['date'] ?? '')),
                    'reference_url' => isset($row['id']) ? sprintf('https://www.usom.gov.tr/adres/%s', (string) $row['id']) : null,
                    'details' => [
                        'code' => $descCode,
                        'category' => $this->expandCode($descCode),
                        'connectionCode' => $connectionCode,
                        'connectionType' => $this->expandCode($connectionCode),
                    ],
                    'label' => 'USOM kritik eşleşmesi',
                ];
            }
        }

        return $this->result('clean', false, 0);
    }

    private function buildHostCandidates(string $host): array
    {
        $parts = explode('.', $host);
        $candidates = [];
        while (count($parts) > 2) {
            array_shift($parts);
            $candidates[] = implode('.', $parts);
        }

        return $candidates;
    }

    private function search(string $query): array
    {
        $url = sprintf('%s?page=1&per-page=25&q=%s', USOM_API_URL, rawurlencode($query));
        $response = $this->http->requestJson(
            'GET',
            $url,
            null,
            ['User-Agent: GuvenlinkLookup/2.0'],
            (int) REMOTE_HTTP_TIMEOUT,
            (int) REMOTE_HTTP_RETRIES
        );

        if (!$response['ok']) {
            $this->guard->failure('usom');
            return $this->result('degraded', false, (int) ($response['latency_ms'] ?? 0)) + ['models' => []];
        }

        $this->guard->success('usom');
        return $this->result('ok', false, (int) $response['latency_ms']) + [
            'models' => $response['data']['models'] ?? [],
        ];
    }

    private function expandCode(string $code): string
    {
        return match ($code) {
            'BP' => 'Bankacılık - Oltalama nedeni ile engellendi',
            'PH' => 'Oltalama nedeni ile engellendi',
            'CA' => 'Siber Saldırı (Port Tarama, Kaba Kuvvet vb.)',
            'MC' => 'Zararlı Yazılım - Komuta Kontrol Merkezi',
            'MD' => 'Zararlı Yazılım Barındıran / Yayan Alan Adı',
            'MI' => 'Zararlı Yazılım Barındıran / Yayan IP',
            default => $code,
        };
    }

    private function normalizeDate(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp === false ? gmdate('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function result(string $status, bool $matched, int $latencyMs): array
    {
        return [
            'service' => 'usom',
            'status' => $status,
            'matched' => $matched,
            'latencyMs' => $latencyMs,
        ];
    }
}

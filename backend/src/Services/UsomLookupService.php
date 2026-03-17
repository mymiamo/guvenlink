<?php

declare(strict_types=1);

namespace App\Services;

final class UsomLookupService
{
    public function __construct(
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function lookup(string $url): ?array
    {
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
            foreach ($payload['models'] ?? [] as $row) {
                $rowType = (string) ($row['type'] ?? 'domain');
                $rowUrl = strtolower(trim((string) ($row['url'] ?? '')));
                if (!$this->isExactMatch($rowType, $rowUrl, $candidate['type'], $candidate['exact'])) {
                    continue;
                }

                return [
                    'type' => $rowType,
                    'source' => 'usom',
                    'reason' => $this->buildReason($row),
                    'updated_at' => $this->normalizeDate((string) ($row['date'] ?? '')),
                    'reference_url' => isset($row['id']) ? sprintf('https://www.usom.gov.tr/adres/%s', (string) $row['id']) : null,
                ];
            }
        }

        return null;
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

    private function isExactMatch(string $rowType, string $rowUrl, string $candidateType, string $candidateExact): bool
    {
        if ($rowType !== $candidateType) {
            return false;
        }

        if ($rowType === 'url') {
            return $rowUrl === $candidateExact;
        }

        return $rowUrl === $candidateExact;
    }

    private function search(string $query): array
    {
        $baseUrl = defined('USOM_API_URL') ? USOM_API_URL : 'https://www.usom.gov.tr/api/address/index';
        $url = sprintf('%s?page=1&per-page=25&q=%s', $baseUrl, rawurlencode($query));
        $context = stream_context_create([
            'http' => [
                'timeout' => 20,
                'header' => "Accept: application/json\r\nUser-Agent: GuvenlikLiveLookup/1.0\r\n",
            ],
        ]);

        $json = file_get_contents($url, false, $context);
        if ($json === false) {
            return ['models' => []];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : ['models' => []];
    }

    private function buildReason(array $row): string
    {
        $parts = array_filter([
            'USOM kaydi nedeniyle zararli olarak isaretlendi.',
            isset($row['desc']) ? 'Kategori: ' . $row['desc'] : null,
            isset($row['connectiontype']) ? 'Tip: ' . $row['connectiontype'] : null,
        ]);

        return implode(' | ', $parts);
    }

    private function normalizeDate(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp === false ? gmdate('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s', $timestamp);
    }
}

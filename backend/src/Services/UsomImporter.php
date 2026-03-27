<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ImportRunRepository;
use App\Repositories\ThreatEntryRepository;
use App\Support\Database;

final class UsomImporter
{
    public function __construct(
        private readonly ThreatEntryRepository $entries = new ThreatEntryRepository(),
        private readonly ImportRunRepository $runs = new ImportRunRepository(),
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function import(): array
    {
        $runId = $this->runs->create('usom');
        $stats = ['added' => 0, 'updated' => 0, 'deactivated' => 0];

        try {
            $pageSize = max(100, defined('USOM_IMPORT_PAGE_SIZE') ? (int) USOM_IMPORT_PAGE_SIZE : 1000);
            $maxPages = defined('USOM_MAX_PAGES') ? (int) USOM_MAX_PAGES : 0;
            $page = 1;
            $seen = [];

            Database::connection()->beginTransaction();

            do {
                if ($maxPages > 0 && $page > $maxPages) {
                    break;
                }

                $payload = $this->fetchPage($page, $pageSize);
                foreach ($payload['models'] as $row) {
                    $normalized = $this->normalizer->normalize((string) $row['url'], $row['type'] === 'domain' ? 'domain' : 'url');
                    $seen[] = [
                        'type' => $normalized['type'],
                        'value' => $normalized['normalized_value'],
                        'usomId' => $row['id'] ?? null,
                    ];
                }
                $page++;
                $pageCount = max(1, (int) ($payload['pageCount'] ?? 1));
            } while (($page - 1) < $pageCount);

            Database::connection()->rollBack();
            $this->runs->finish($runId, 'success', $stats, 'USOM canlı API kullanılıyor; SQL import yapılmadı.');
            return ['runId' => $runId, 'mode' => 'live_api', 'sampleCount' => count($seen)];
        } catch (\Throwable $exception) {
            if (Database::connection()->inTransaction()) {
                Database::connection()->rollBack();
            }
            $this->runs->finish($runId, 'failed', $stats, $exception->getMessage());
            throw $exception;
        }
    }

    private function fetchPage(int $page, int $pageSize): array
    {
        $baseUrl = defined('USOM_API_URL') ? USOM_API_URL : 'https://www.usom.gov.tr/api/address/index';
        $url = sprintf('%s?page=%d&per-page=%d', $baseUrl, $page, $pageSize);
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'header' => "Accept: application/json\r\nUser-Agent: GuvenlikImporter/1.0\r\n",
            ],
        ]);

        $json = file_get_contents($url, false, $context);
        if ($json === false) {
            throw new \RuntimeException('USOM verisi alınamadı: ' . $url);
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['models'])) {
            throw new \RuntimeException('USOM veri biçimi beklenen formatta değil.');
        }

        return $decoded;
    }

    private function buildReason(array $row): string
    {
        return 'USOM canlı kaydı';
    }

    private function normalizeDate(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp === false ? gmdate('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s', $timestamp);
    }
}

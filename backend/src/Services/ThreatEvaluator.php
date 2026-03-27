<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ThreatEntryRepository;
use App\Support\ShortenerPolicy;

final class ThreatEvaluator
{
    public function __construct(
        private readonly ?ThreatEntryRepository $entries = null,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
        private readonly UsomLookupService $usomLookup = new UsomLookupService(),
        private readonly SafeBrowsingService $safeBrowsing = new SafeBrowsingService(),
        private readonly VirusTotalService $virusTotal = new VirusTotalService(),
        private readonly ThreatAnalysisCacheService $cache = new ThreatAnalysisCacheService(),
    ) {
    }

    public function evaluate(string $url): array
    {
        $normalized = $this->normalizer->normalize($url, 'url');

        return $this->cache->remember($normalized['normalized_url'], function () use ($url, $normalized): array {
            $startedAt = microtime(true);
            $checks = [];
            $signals = [];
            $degraded = [];

            $usomMatch = $this->usomLookup->lookup($url);
            $checks[] = $this->formatCheck($usomMatch);
            if (($usomMatch['status'] ?? '') === 'degraded') {
                $degraded[] = 'usom';
            }
            if (($usomMatch['status'] ?? '') === 'match') {
                $signals[] = $this->signal(
                    'usom',
                    'USOM kritik eşleşmesi',
                    'high',
                    (string) ($usomMatch['details']['code'] ?? 'USOM'),
                    'USOM kaydı bu adresi doğrudan zararlı olarak işaretliyor.',
                    100
                );

                return $this->buildResult(
                    'malicious',
                    100,
                    'high',
                    $normalized,
                    (string) ($usomMatch['type'] ?? 'url'),
                    'usom',
                    [$usomMatch['reason'] ?? 'USOM kaydı bulundu.'],
                    (string) ($usomMatch['updated_at'] ?? gmdate('Y-m-d H:i:s')),
                    $usomMatch['reference_url'] ?? null,
                    $checks,
                    $signals,
                    $degraded,
                    (int) round((microtime(true) - $startedAt) * 1000),
                    $usomMatch['details'] ?? null
                );
            }

            $repository = $this->entries ?? new ThreatEntryRepository();
            $matches = $repository->findActiveMatch($normalized['normalized_url'], $normalized['host']);

            $whiteMatch = $this->firstMatchByStatus($matches, 'white');
            if ($whiteMatch !== null) {
                $signals[] = $this->signal('manual', 'Yerel beyaz liste', 'low', 'WHITE', 'Bu adres beyaz listede yer alıyor.', 0);
                return $this->buildResult(
                    'safe',
                    0,
                    'high',
                    $normalized,
                    $whiteMatch['type'],
                    $whiteMatch['source'],
                    ['Yerel beyaz liste eşleşmesi bulundu.'],
                    $whiteMatch['updated_at'],
                    null,
                    $checks,
                    $signals,
                    $degraded,
                    (int) round((microtime(true) - $startedAt) * 1000)
                );
            }

            $blackMatch = $this->firstMatchByStatus($matches, 'black');
            if ($blackMatch !== null) {
                $signals[] = $this->signal('manual', 'Yerel kara liste', 'high', 'BLACK', 'Bu adres yerel kara listede bulunuyor.', 95);
                return $this->buildResult(
                    'malicious',
                    95,
                    'high',
                    $normalized,
                    $blackMatch['type'],
                    $blackMatch['source'],
                    [$blackMatch['reason'] ?: 'Yerel kara liste eşleşmesi bulundu.'],
                    $blackMatch['updated_at'],
                    null,
                    $checks,
                    $signals,
                    $degraded,
                    (int) round((microtime(true) - $startedAt) * 1000)
                );
            }

            $suspiciousMatch = $this->firstMatchByStatus($matches, 'suspicious');
            if ($suspiciousMatch !== null) {
                $signals[] = $this->signal('manual', 'Yerel şüpheli kayıt', 'medium', 'SUSPICIOUS', 'Bu adres yerel şüpheli kayıtlar içinde bulunuyor.', 72);
                return $this->buildResult(
                    'suspicious',
                    72,
                    'high',
                    $normalized,
                    $suspiciousMatch['type'],
                    $suspiciousMatch['source'],
                    [$suspiciousMatch['reason'] ?: 'Yerel şüpheli kayıt eşleşmesi bulundu.'],
                    $suspiciousMatch['updated_at'],
                    null,
                    $checks,
                    $signals,
                    $degraded,
                    (int) round((microtime(true) - $startedAt) * 1000)
                );
            }

            $safeBrowsing = $this->safeBrowsing->checkUrl($url);
            $checks[] = $this->formatCheck($safeBrowsing);
            if (($safeBrowsing['status'] ?? '') === 'degraded') {
                $degraded[] = 'safe-browsing';
            } elseif (($safeBrowsing['status'] ?? '') === 'match') {
                $signals[] = $this->signal(
                    'safe-browsing',
                    $safeBrowsing['label'] ?? 'Google Safe Browsing eşleşmesi',
                    ($safeBrowsing['verdict'] ?? 'suspicious') === 'malicious' ? 'high' : 'medium',
                    (string) ($safeBrowsing['threatType'] ?? 'SAFE_BROWSING'),
                    $safeBrowsing['label'] ?? 'Google Safe Browsing bu adresi işaretledi.',
                    (int) ($safeBrowsing['score'] ?? 0)
                );
            }

            $virusTotal = $this->virusTotal->analyzeUrl($url);
            $checks[] = $this->formatCheck($virusTotal);
            if (($virusTotal['status'] ?? '') === 'degraded') {
                $degraded[] = 'virustotal';
            } elseif (($virusTotal['status'] ?? '') === 'match') {
                $signals[] = $this->signal(
                    'virustotal',
                    'VirusTotal motor eşleşmesi',
                    ($virusTotal['verdict'] ?? 'suspicious') === 'malicious' ? 'high' : 'medium',
                    'VT',
                    $virusTotal['label'] ?? 'VirusTotal bu adresi işaretledi.',
                    (int) ($virusTotal['score'] ?? 0)
                );
            }

            foreach ($this->heuristicSignals($url, $normalized) as $signal) {
                $signals[] = $signal;
            }

            $score = $this->calculateScore($signals);
            $verdict = $this->determineVerdict($score, $degraded);
            if ($this->hasOnlyTrustedShortenerSignal($signals)) {
                $verdict = 'safe';
            }
            $confidence = $this->determineConfidence($signals, $degraded, $verdict);
            $reasons = $this->buildReasons($signals, $degraded, $verdict);
            $referenceUrl = ($virusTotal['status'] ?? '') === 'match' ? ($virusTotal['permalink'] ?? null) : null;

            return $this->buildResult(
                $verdict,
                $score,
                $confidence,
                $normalized,
                null,
                'guvenlink',
                $reasons,
                gmdate('Y-m-d H:i:s'),
                $referenceUrl,
                $checks,
                $signals,
                $degraded,
                (int) round((microtime(true) - $startedAt) * 1000)
            );
        });
    }

    private function buildResult(
        string $verdict,
        int $score,
        string $confidence,
        array $normalized,
        ?string $matchedBy,
        string $source,
        array $reasons,
        string $updatedAt,
        ?string $referenceUrl,
        array $checks,
        array $signals,
        array $degraded,
        int $latencyMs,
        ?array $usomDetails = null
    ): array {
        return [
            'normalizedUrl' => $normalized['normalized_url'],
            'hostname' => $normalized['host'],
            'verdict' => $verdict,
            'matchedBy' => $matchedBy,
            'source' => $source,
            'reasons' => array_values(array_unique(array_filter($reasons))),
            'updatedAt' => $updatedAt,
            'referenceUrl' => $referenceUrl,
            'usomDetails' => $usomDetails,
            'checks' => $checks,
            'signals' => $signals,
            'degraded' => array_values(array_unique($degraded)),
            'latencyMs' => $latencyMs,
            'score' => $score,
            'confidence' => $confidence,
        ];
    }

    private function heuristicSignals(string $originalUrl, array $normalized): array
    {
        $signals = [];
        $host = $normalized['host'];
        $lowerOriginal = strtolower($originalUrl);
        $parts = explode('.', $host);
        $tld = end($parts) ?: '';

        if (str_starts_with($lowerOriginal, 'http://')) {
            $signals[] = $this->signal('heuristic', 'HTTP kullanımı', 'low', 'HTTP', 'Bağlantı şifrelenmemiş HTTP kullanıyor.', 15);
        }

        if (str_contains($host, 'xn--')) {
            $signals[] = $this->signal('heuristic', 'Punycode / IDN', 'medium', 'PUNYCODE', 'Punycode veya IDN alan adı tespit edildi.', 25);
        }

        $shortenerCategory = ShortenerPolicy::category($host);
        if ($shortenerCategory === 'risky') {
            $signals[] = $this->signal('heuristic', 'Riskli kısa link servisi', 'medium', 'SHORTENER_RISKY', 'Riskli link kısaltma servisi kullanılıyor.', 40);
        } elseif ($shortenerCategory === 'safe') {
            $signals[] = $this->signal('heuristic', 'Güvenilir kısa link servisi', 'low', 'SHORTENER_SAFE', 'Güvenilir link kısaltma servisi kullanılıyor.', 0);
        }

        $suspiciousTlds = ['zip', 'mov', 'country', 'click', 'top', 'gq', 'tk', 'work', 'support', 'shop', 'xyz', 'rest', 'live', 'vip', 'cam'];
        if ($tld !== '' && in_array($tld, $suspiciousTlds, true)) {
            $signals[] = $this->signal('heuristic', 'Riskli TLD', 'medium', 'TLD', "Riskli TLD tespit edildi (.{$tld}).", 18);
        }

        if (strlen($normalized['normalized_url']) > 180 || preg_match('/(%[0-9a-f]{2}){6,}/i', $lowerOriginal) === 1) {
            $signals[] = $this->signal('heuristic', 'Gizlenmiş veya uzun URL', 'medium', 'OBFUSCATED', 'URL gizlenmiş veya aşırı uzun görünüyor.', 18);
        }

        if (count($parts) > 5) {
            $signals[] = $this->signal('heuristic', 'Aşırı alt alan adı', 'low', 'SUBDOMAIN', 'Aşırı sayıda alt alan adı tespit edildi.', 12);
        }

        return $signals;
    }

    private function calculateScore(array $signals): int
    {
        $score = 0;
        foreach ($signals as $signal) {
            $score += (int) ($signal['weight'] ?? 0);
        }

        return min(100, $score);
    }

    private function determineVerdict(int $score, array $degraded): string
    {
        if ($score >= 80) {
            return 'malicious';
        }

        if ($score >= 35) {
            return 'suspicious';
        }

        if ($degraded !== []) {
            return 'unknown';
        }

        return 'safe';
    }

    private function determineConfidence(array $signals, array $degraded, string $verdict): string
    {
        if ($this->hasOnlyTrustedShortenerSignal($signals)) {
            return 'high';
        }

        $highCount = count(array_filter($signals, static fn (array $signal): bool => ($signal['severity'] ?? '') === 'high'));
        if ($highCount > 0 || count($signals) >= 3) {
            return 'high';
        }

        if ($verdict === 'unknown' || $degraded !== []) {
            return 'low';
        }

        if ($signals !== []) {
            return 'medium';
        }

        return 'medium';
    }

    private function buildReasons(array $signals, array $degraded, string $verdict): array
    {
        if ($signals !== []) {
            return array_values(array_map(static fn (array $signal): string => (string) $signal['description'], $signals));
        }

        if ($degraded !== []) {
            return ['Dış servislerin bir kısmına erişilemediği için sonuç belirsiz işaretlendi.'];
        }

        return [$verdict === 'safe' ? 'Herhangi bir risk tespiti yapılmadı.' : 'Adres incelendi.'];
    }

    private function hasOnlyTrustedShortenerSignal(array $signals): bool
    {
        if ($signals === []) {
            return false;
        }

        foreach ($signals as $signal) {
            if (($signal['code'] ?? null) !== 'SHORTENER_SAFE') {
                return false;
            }
        }

        return true;
    }

    private function formatCheck(array $serviceResult): array
    {
        return [
            'service' => (string) ($serviceResult['service'] ?? 'unknown'),
            'status' => (string) ($serviceResult['status'] ?? 'unknown'),
            'latencyMs' => (int) ($serviceResult['latencyMs'] ?? 0),
            'matched' => (bool) ($serviceResult['matched'] ?? false),
        ];
    }

    private function signal(string $source, string $label, string $severity, string $code, string $description, int $weight): array
    {
        return [
            'source' => $source,
            'label' => $label,
            'severity' => $severity,
            'code' => $code,
            'description' => $description,
            'weight' => $weight,
        ];
    }

    private function firstMatchByStatus(array $matches, string $status): ?array
    {
        foreach ($matches as $match) {
            if (($match['status'] ?? null) === $status) {
                return $match;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ThreatEntryRepository;

final class ThreatEvaluator
{
    public function __construct(
        private readonly ?ThreatEntryRepository $entries = null,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
        private readonly UsomLookupService $usomLookup = new UsomLookupService(),
    ) {
    }

    public function evaluate(string $url): array
    {
        $normalized = $this->normalizer->normalize($url, 'url');
        $usomMatch = $this->usomLookup->lookup($url);
        if ($usomMatch !== null) {
            return $this->buildResult('malicious', $normalized, $usomMatch['type'], $usomMatch['source'], [$usomMatch['reason']], $usomMatch['updated_at'], $usomMatch['reference_url']);
        }

        $matches = [];
        try {
            $repository = $this->entries ?? new ThreatEntryRepository();
            $matches = $repository->findActiveMatch($normalized['normalized_url'], $normalized['host']);
        } catch (\Throwable) {
            $matches = [];
        }

        $whiteMatches = array_values(array_filter($matches, static fn (array $row): bool => $row['status'] === 'white'));
        if ($whiteMatches !== []) {
            $top = $whiteMatches[0];
            return $this->buildResult('safe', $normalized, $top['type'], $top['source'], ['Beyaz liste eslesmesi bulundu.'], $top['updated_at'], null);
        }

        $blackMatches = array_values(array_filter($matches, static fn (array $row): bool => $row['status'] === 'black'));
        if ($blackMatches !== []) {
            $top = $blackMatches[0];
            return $this->buildResult('malicious', $normalized, $top['type'], $top['source'], [$top['reason'] ?: 'Kara liste eslesmesi bulundu.'], $top['updated_at'], null);
        }

        $heuristics = $this->heuristicReasons($url, $normalized);
        if ($heuristics !== []) {
            return $this->buildResult('suspicious', $normalized, 'heuristic', 'mymiamo.net', $heuristics, gmdate('Y-m-d H:i:s'), null);
        }

        return $this->buildResult('safe', $normalized, null, 'mymiamo.net', ['Kara liste eslesmesi bulunmadi.'], gmdate('Y-m-d H:i:s'), null);
    }

    private function buildResult(string $verdict, array $normalized, ?string $matchedBy, string $source, array $reasons, string $updatedAt, ?string $referenceUrl): array
    {
        return [
            'normalizedUrl' => $normalized['normalized_url'],
            'hostname' => $normalized['host'],
            'verdict' => $verdict,
            'matchedBy' => $matchedBy,
            'source' => $source,
            'reasons' => array_values(array_unique(array_filter($reasons))),
            'updatedAt' => $updatedAt,
            'referenceUrl' => $referenceUrl,
        ];
    }

    private function heuristicReasons(string $originalUrl, array $normalized): array
    {
        $reasons = [];
        $host = $normalized['host'];
        $lowerOriginal = strtolower($originalUrl);

        if (str_starts_with(strtolower($originalUrl), 'http://')) {
            $reasons[] = 'Baglanti sifrelenmemis HTTP kullaniyor.';
        }

        if (str_contains($host, 'xn--')) {
            $reasons[] = 'Punycode/IDN alan adi tespit edildi.';
        }

        $shorteners = ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'cutt.ly', 'shorturl.at'];
        if (in_array($host, $shorteners, true)) {
            $reasons[] = 'Kisa baglanti servisi kullaniliyor.';
        }

        $suspiciousTlds = ['zip', 'mov', 'country', 'click', 'top', 'gq', 'tk', 'work', 'support', 'shop', 'xyz'];
        $tld = pathinfo($host, PATHINFO_EXTENSION);
        if ($tld !== '' && in_array($tld, $suspiciousTlds, true)) {
            $reasons[] = 'Riskli TLD/desen tespit edildi.';
        }

        if (strlen($normalized['normalized_url']) > 180 || preg_match('/(%[0-9a-f]{2}){6,}/i', $lowerOriginal) === 1) {
            $reasons[] = 'URL gizlenmis veya asiri uzun gorunuyor.';
        }

        return $reasons;
    }
}

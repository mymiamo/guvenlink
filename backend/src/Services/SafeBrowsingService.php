<?php

declare(strict_types=1);

namespace App\Services;

final class SafeBrowsingService
{
    private const ENDPOINT = 'https://safebrowsing.googleapis.com/v4/threatMatches:find';

    public function __construct(
        private readonly HttpClient $http = new HttpClient(),
        private readonly RemoteServiceGuard $guard = new RemoteServiceGuard(),
        private readonly string $apiKey = SAFE_BROWSING_API_KEY,
    ) {
    }

    public function enabled(): bool
    {
        return $this->apiKey !== '';
    }

    public function checkUrl(string $url): array
    {
        if (!$this->enabled()) {
            return $this->result('disabled', false, 0);
        }

        if ($this->guard->isOpen('safe-browsing')) {
            return $this->result('degraded', false, 0);
        }

        $payload = [
            'client' => [
                'clientId' => SAFE_BROWSING_CLIENT_ID,
                'clientVersion' => SAFE_BROWSING_CLIENT_VERSION,
            ],
            'threatInfo' => [
                'threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'],
                'platformTypes' => ['ANY_PLATFORM'],
                'threatEntryTypes' => ['URL'],
                'threatEntries' => [['url' => $url]],
            ],
        ];

        $response = $this->http->requestJson(
            'POST',
            self::ENDPOINT . '?key=' . urlencode($this->apiKey),
            $payload,
            [],
            (int) REMOTE_HTTP_TIMEOUT,
            (int) REMOTE_HTTP_RETRIES
        );

        if (!$response['ok']) {
            $this->guard->failure('safe-browsing');
            return $this->result('degraded', false, (int) ($response['latency_ms'] ?? 0));
        }

        $this->guard->success('safe-browsing');
        $match = $response['data']['matches'][0] ?? null;
        if (!is_array($match)) {
            return $this->result('clean', false, (int) $response['latency_ms']);
        }

        $threatType = (string) ($match['threatType'] ?? 'MALWARE');
        $verdict = in_array($threatType, ['MALWARE', 'POTENTIALLY_HARMFUL_APPLICATION'], true) ? 'malicious' : 'suspicious';
        $score = $verdict === 'malicious' ? 88 : 72;

        return $this->result('match', true, (int) $response['latency_ms']) + [
            'verdict' => $verdict,
            'score' => $score,
            'confidence' => 'high',
            'threatType' => $threatType,
            'label' => self::threatTypeLabel($threatType),
        ];
    }

    public static function threatTypeLabel(string $threatType): string
    {
        return match ($threatType) {
            'MALWARE' => 'Zararlı yazılım (Google Safe Browsing)',
            'SOCIAL_ENGINEERING' => 'Kimlik avı / oltalama (Google Safe Browsing)',
            'UNWANTED_SOFTWARE' => 'İstenmeyen yazılım (Google Safe Browsing)',
            'POTENTIALLY_HARMFUL_APPLICATION' => 'Potansiyel zararlı uygulama (Google Safe Browsing)',
            default => "Tehdit tespit edildi: {$threatType} (Google Safe Browsing)",
        };
    }

    private function result(string $status, bool $matched, int $latencyMs): array
    {
        return [
            'service' => 'safe-browsing',
            'status' => $status,
            'matched' => $matched,
            'latencyMs' => $latencyMs,
        ];
    }
}

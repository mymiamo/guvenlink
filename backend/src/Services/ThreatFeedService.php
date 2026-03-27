<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ThreatEntryRepository;

final class ThreatFeedService
{
    public function __construct(
        private readonly ThreatEntryRepository $entries = new ThreatEntryRepository(),
    ) {
    }

    public function page(int $page, int $perPage, ?string $since = null): array
    {
        $total = $this->entries->totalActiveCount();
        $lastUpdated = $this->entries->latestUpdatedAt();
        $pageCount = (int) ceil($total / max(1, $perPage));
        $version = sha1(($lastUpdated ?? 'none') . ':' . $total);

        if ($since !== null) {
            $changes = $this->entries->changesSince($since);
            $updated = [];
            $removed = [];
            foreach ($changes as $row) {
                if ((int) $row['is_active'] === 1) {
                    $updated[] = $row;
                } else {
                    $removed[] = [
                        'value' => $row['value'],
                        'type' => $row['type'],
                    ];
                }
            }

            return [
                'version' => $version,
                'generatedAt' => gmdate('c'),
                'syncToken' => $lastUpdated,
                'page' => 1,
                'perPage' => $perPage,
                'pageCount' => 1,
                'hasMore' => false,
                'mode' => 'delta',
                'updated' => $updated,
                'removed' => $removed,
            ];
        }

        return [
            'version' => $version,
            'generatedAt' => gmdate('c'),
            'syncToken' => $lastUpdated,
            'page' => $page,
            'perPage' => $perPage,
            'pageCount' => $pageCount,
            'hasMore' => $page < $pageCount,
            'mode' => 'snapshot',
            'entries' => $this->entries->feedPage($page, $perPage),
            'updated' => [],
            'removed' => [],
        ];
    }
}

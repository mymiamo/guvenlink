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

    public function page(int $page, int $perPage): array
    {
        $total = $this->entries->totalActiveCount();
        $entries = $this->entries->feedPage($page, $perPage);
        $lastUpdated = $this->entries->latestUpdatedAt();
        $pageCount = (int) ceil($total / max(1, $perPage));

        return [
            'version' => sha1(($lastUpdated ?? 'none') . ':' . $total),
            'generatedAt' => gmdate('c'),
            'page' => $page,
            'perPage' => $perPage,
            'pageCount' => $pageCount,
            'hasMore' => $page < $pageCount,
            'entries' => $entries,
        ];
    }
}


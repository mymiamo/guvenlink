<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Logger;
use App\Support\Request;

final class RequestAuditService
{
    public function log(Request $request, int $status, float $startedAt): void
    {
        Logger::info('request', [
            'method' => $request->method,
            'path' => $request->path,
            'status' => $status,
            'ip' => $request->ip(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}

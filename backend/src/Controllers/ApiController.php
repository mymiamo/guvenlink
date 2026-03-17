<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ImportRunRepository;
use App\Repositories\ThreatEntryRepository;
use App\Services\AuthService;
use App\Services\ThreatEvaluator;
use App\Services\ThreatFeedService;
use App\Services\UrlNormalizer;
use App\Services\UsomImporter;
use App\Support\Request;
use App\Support\Response;

final class ApiController
{
    public function check(Request $request): void
    {
        $url = (string) $request->input('url', '');
        if ($url === '') {
            Response::json(['error' => 'url zorunlu alandir.'], 422);
            return;
        }

        $evaluator = new ThreatEvaluator();
        Response::json($evaluator->evaluate($url));
    }

    public function feed(Request $request): void
    {
        $page = max(1, (int) $request->input('page', 1));
        $defaultPerPage = defined('EXTENSION_FEED_PAGE_SIZE') ? (int) EXTENSION_FEED_PAGE_SIZE : 5000;
        $perPage = max(100, min(10000, (int) $request->input('perPage', $defaultPerPage)));

        $feed = new ThreatFeedService();
        Response::json($feed->page($page, $perPage));
    }

    public function meta(): void
    {
        $entries = new ThreatEntryRepository();
        $runs = new ImportRunRepository();

        Response::json([
            'activeEntryCount' => $entries->totalActiveCount(),
            'latestUpdatedAt' => $entries->latestUpdatedAt(),
            'latestImport' => $runs->latest('usom'),
        ]);
    }

    public function login(Request $request): void
    {
        $auth = new AuthService();
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if (!$auth->attempt($email, $password)) {
            Response::json(['error' => 'Gecersiz kullanici bilgileri.'], 401);
            return;
        }

        Response::json([
            'ok' => true,
            'user' => $auth->user(),
        ]);
    }

    public function listEntries(Request $request): void
    {
        $this->assertAuthenticated();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $filters = [
            'q' => $request->input('q'),
            'status' => $request->input('status'),
            'type' => $request->input('type'),
            'source' => $request->input('source'),
            'is_active' => $request->input('is_active'),
        ];

        $repository = new ThreatEntryRepository();
        $total = $repository->count($filters);
        $rows = $repository->search($filters, $perPage, ($page - 1) * $perPage);

        Response::json([
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'items' => $rows,
        ]);
    }

    public function createEntry(Request $request): void
    {
        $this->assertAuthenticated();
        $payload = $this->normalizeManualEntryPayload($request);
        $repository = new ThreatEntryRepository();
        $id = $repository->create($payload);

        Response::json([
            'ok' => true,
            'id' => $id,
        ], 201);
    }

    public function updateEntry(Request $request): void
    {
        $this->assertAuthenticated();
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            Response::json(['error' => 'id zorunludur.'], 422);
            return;
        }

        $payload = $this->normalizeManualEntryPayload($request);
        $repository = new ThreatEntryRepository();
        $repository->update($id, $payload);

        Response::json(['ok' => true]);
    }

    public function importUsom(): void
    {
        $this->assertAuthenticated();
        $importer = new UsomImporter();
        Response::json([
            'ok' => true,
            'result' => $importer->import(),
        ]);
    }

    private function normalizeManualEntryPayload(Request $request): array
    {
        $normalizer = new UrlNormalizer();
        $normalized = $normalizer->normalize((string) $request->input('match_value', ''), (string) $request->input('type', 'domain'));

        return [
            'type' => $normalized['type'],
            'match_value' => (string) $request->input('match_value', ''),
            'normalized_value' => $normalized['normalized_value'],
            'normalized_hash' => $normalized['normalized_hash'],
            'status' => (string) $request->input('status', 'black'),
            'source' => 'manual',
            'reason' => (string) $request->input('reason', ''),
            'is_active' => (int) ((string) $request->input('is_active', '1') === '1'),
            'first_seen_at' => $request->input('first_seen_at') ?: gmdate('Y-m-d H:i:s'),
            'last_seen_at' => $request->input('last_seen_at') ?: gmdate('Y-m-d H:i:s'),
        ];
    }

    private function assertAuthenticated(): void
    {
        $auth = new AuthService();
        if (!$auth->check()) {
            Response::json(['error' => 'Yetkisiz istek.'], 401);
            exit;
        }
    }
}

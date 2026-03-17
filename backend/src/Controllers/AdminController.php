<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ImportRunRepository;
use App\Repositories\ThreatEntryRepository;
use App\Services\AuthService;
use App\Services\UrlNormalizer;
use App\Services\UsomImporter;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

final class AdminController
{
    public function loginForm(?string $error = null): void
    {
        Response::html(View::render('login', ['error' => $error]));
    }

    public function login(Request $request): void
    {
        $auth = new AuthService();
        if ($auth->attempt((string) $request->input('email', ''), (string) $request->input('password', ''))) {
            Response::redirect('/admin');
            return;
        }

        $this->loginForm('Gecersiz kullanici bilgileri.');
    }

    public function logout(): void
    {
        $auth = new AuthService();
        $auth->logout();
        Response::redirect('/admin/login');
    }

    public function dashboard(Request $request): void
    {
        $this->guard();
        $filters = [
            'q' => $request->input('q'),
            'status' => $request->input('status'),
            'type' => $request->input('type'),
            'source' => $request->input('source'),
            'is_active' => $request->input('is_active'),
        ];

        $entries = new ThreatEntryRepository();
        $runs = new ImportRunRepository();

        Response::html(View::render('dashboard', [
            'user' => (new AuthService())->user(),
            'entries' => $entries->search($filters, 100, 0),
            'total' => $entries->count($filters),
            'filters' => $filters,
            'latestImport' => $runs->latest('usom'),
            'recentImports' => $runs->recent(10),
        ]));
    }

    public function saveEntry(Request $request): void
    {
        $this->guard();
        $repository = new ThreatEntryRepository();
        $normalizer = new UrlNormalizer();
        $normalized = $normalizer->normalize((string) $request->input('match_value', ''), (string) $request->input('type', 'domain'));
        $payload = [
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

        $id = (int) $request->input('id', 0);
        if ($id > 0) {
            $repository->update($id, $payload);
        } else {
            $repository->create($payload);
        }

        Response::redirect('/admin');
    }

    public function runImport(): void
    {
        $this->guard();
        $importer = new UsomImporter();
        $importer->import();
        Response::redirect('/admin');
    }

    private function guard(): void
    {
        $auth = new AuthService();
        if (!$auth->check()) {
            Response::redirect('/admin/login');
        }
    }
}

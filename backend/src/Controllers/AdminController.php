<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AdminAuditLogRepository;
use App\Repositories\AdminUserRepository;
use App\Repositories\ImportRunRepository;
use App\Repositories\SiteReportRepository;
use App\Repositories\ThreatEntryRepository;
use App\Services\AuthService;
use App\Services\CsrfService;
use App\Services\InstallService;
use App\Services\RemoteServiceGuard;
use App\Services\ThreatAnalysisCacheService;
use App\Services\ThreatEvaluator;
use App\Services\UrlNormalizer;
use App\Services\UsomImporter;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

final class AdminController
{
    public function loginForm(?string $error = null): void
    {
        $installService = new InstallService();
        Response::html(View::render('login', [
            'error' => $error,
            'csrfToken' => (new CsrfService())->token(),
            'canInstall' => $installService->canBootstrap(),
        ]));
    }

    public function installForm(?string $error = null): void
    {
        $installService = new InstallService();
        if (!$installService->canBootstrap()) {
            Response::redirect('/admin/login');
        }

        Response::html(View::render('install', [
            'error' => $error,
            'csrfToken' => (new CsrfService())->token(),
        ]));
    }

    public function install(Request $request): void
    {
        $installService = new InstallService();
        if (!$installService->canBootstrap()) {
            Response::redirect('/admin/login');
            return;
        }

        if (!(new CsrfService())->validate((string) $request->input('_csrf', ''))) {
            $this->installForm('Geçersiz güvenlik belirteci.');
            return;
        }

        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $passwordRepeat = (string) $request->input('password_repeat', '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->installForm('Geçerli bir e-posta girin.');
            return;
        }

        if ($password === '' || strlen($password) < 12) {
            $this->installForm('Şifre en az 12 karakter olmalı.');
            return;
        }

        if ($password !== $passwordRepeat) {
            $this->installForm('Şifre tekrar alanı eşleşmiyor.');
            return;
        }

        $installService->createAdmin($email, $password);
        Response::redirect('/admin/login');
    }

    public function login(Request $request): void
    {
        if (!(new CsrfService())->validate((string) $request->input('_csrf', ''))) {
            $this->loginForm('Geçersiz güvenlik belirteci.');
            return;
        }

        $auth = new AuthService();
        if ($auth->attempt((string) $request->input('email', ''), (string) $request->input('password', ''))) {
            Response::redirect('/admin');
            return;
        }

        $this->loginForm('Geçersiz kullanıcı bilgileri.');
    }

    public function logout(): void
    {
        (new AuthService())->logout();
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
        $editId = (int) $request->input('edit', 0);

        $entries = new ThreatEntryRepository();
        $runs = new ImportRunRepository();
        $reports = new SiteReportRepository();
        $auditLogs = new AdminAuditLogRepository();
        $editEntry = $editId > 0 ? $entries->findById($editId) : null;
        $latestReports = $reports->latest(30);

        Response::html(View::render('dashboard', [
            'user' => (new AuthService())->user(),
            'entries' => $entries->search($filters, 100, 0),
            'total' => $entries->count($filters),
            'filters' => $filters,
            'latestImport' => $runs->latest('usom'),
            'recentImports' => $runs->recent(10),
            'reports' => $latestReports,
            'reportSummary' => $reports->summary(),
            'reportAnalyses' => $this->analyzeReports($latestReports),
            'editEntry' => $editEntry,
            'csrfToken' => (new CsrfService())->token(),
            'health' => $this->healthSummary(),
            'auditLogs' => $auditLogs->latest(15),
        ]));
    }

    public function saveEntry(Request $request): void
    {
        $this->guard();
        $this->assertCsrf($request);

        $repository = new ThreatEntryRepository();
        $normalizer = new UrlNormalizer();
        $normalized = $normalizer->normalize((string) $request->input('match_value', ''), (string) $request->input('type', 'domain'));
        $id = (int) $request->input('id', 0);
        $existing = $id > 0 ? $repository->findById($id) : null;
        $payload = [
            'type' => $normalized['type'],
            'match_value' => (string) $request->input('match_value', ''),
            'normalized_value' => $normalized['normalized_value'],
            'normalized_hash' => $normalized['normalized_hash'],
            'status' => $this->normalizeEntryStatus((string) $request->input('status', 'black')),
            'source' => $existing['source'] ?? 'manual',
            'reason' => (string) $request->input('reason', ''),
            'is_active' => (int) ((string) $request->input('is_active', '1') === '1'),
            'first_seen_at' => $existing['first_seen_at'] ?? ($request->input('first_seen_at') ?: gmdate('Y-m-d H:i:s')),
            'last_seen_at' => $request->input('last_seen_at') ?: gmdate('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $repository->update($id, $payload);
            $this->audit('entry.updated', 'threat_entry', $id, [
                'status' => $payload['status'],
                'is_active' => $payload['is_active'],
                'match_value' => $payload['match_value'],
            ]);
        } else {
            $id = $repository->create($payload);
            $this->audit('entry.created', 'threat_entry', $id, [
                'status' => $payload['status'],
                'match_value' => $payload['match_value'],
            ]);
        }

        (new ThreatAnalysisCacheService())->invalidateAll();
        Response::redirect('/admin');
    }

    public function runImport(Request $request): void
    {
        $this->guard();
        $this->assertCsrf($request);
        $result = (new UsomImporter())->import();
        (new ThreatAnalysisCacheService())->invalidateAll();
        $this->audit('usom.import', 'import_run', null, $result);
        Response::redirect('/admin');
    }

    public function approveReport(Request $request): void
    {
        $this->guard();
        $this->assertCsrf($request);
        $reportId = (int) $request->input('report_id', 0);
        if ($reportId <= 0) {
            Response::redirect('/admin');
            return;
        }

        $reports = new SiteReportRepository();
        $report = $reports->findById($reportId);
        if ($report === null) {
            Response::redirect('/admin');
            return;
        }

        $repository = new ThreatEntryRepository();
        $normalizer = new UrlNormalizer();
        $normalized = $normalizer->normalize($report['report_url'], $report['report_type']);
        $entryStatus = $this->normalizeEntryStatus((string) $request->input('entry_status', 'black'));
        $entryId = $repository->upsertManualEntry([
            'type' => $normalized['type'],
            'match_value' => $report['report_url'],
            'normalized_value' => $normalized['normalized_value'],
            'normalized_hash' => $normalized['normalized_hash'],
            'status' => $entryStatus,
            'source' => 'manual',
            'reason' => $report['note'] ?: 'Kullanıcı raporu üzerinden işlendi.',
            'is_active' => 1,
            'first_seen_at' => gmdate('Y-m-d H:i:s'),
            'last_seen_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $reportStatus = $entryStatus === 'white' ? 'false_positive' : 'confirmed_malicious';
        $reports->markReviewed($reportId, $reportStatus);
        (new ThreatAnalysisCacheService())->invalidateAll();

        $this->audit('report.processed', 'site_report', $reportId, [
            'entry_id' => $entryId,
            'entry_status' => $entryStatus,
            'report_status' => $reportStatus,
            'url' => $report['report_url'],
        ]);

        Response::redirect('/admin');
    }

    public function reviewReport(Request $request): void
    {
        $this->guard();
        $this->assertCsrf($request);
        $reportId = (int) $request->input('report_id', 0);
        if ($reportId > 0) {
            $reports = new SiteReportRepository();
            $reports->markReviewed($reportId, 'needs_review');
            $this->audit('report.needs_review', 'site_report', $reportId);
        }

        Response::redirect('/admin');
    }

    public function rejectReport(Request $request): void
    {
        $this->guard();
        $this->assertCsrf($request);
        $reportId = (int) $request->input('report_id', 0);
        if ($reportId > 0) {
            $reports = new SiteReportRepository();
            $reports->markReviewed($reportId, 'rejected');
            $this->audit('report.rejected', 'site_report', $reportId);
        }

        Response::redirect('/admin');
    }

    private function analyzeReports(array $reports): array
    {
        $analyses = [];
        $evaluator = new ThreatEvaluator();
        foreach ($reports as $report) {
            try {
                $analyses[(int) $report['id']] = $evaluator->evaluate((string) $report['report_url']);
            } catch (\Throwable) {
                $analyses[(int) $report['id']] = null;
            }
        }

        return $analyses;
    }

    private function guard(): void
    {
        if (!(new AuthService())->check()) {
            Response::redirect('/admin/login');
        }
    }

    private function assertCsrf(Request $request): void
    {
        if (!(new CsrfService())->validate((string) $request->input('_csrf', ''))) {
            Response::html('Geçersiz CSRF belirteci.', 419);
            exit;
        }
    }

    private function normalizeEntryStatus(string $status): string
    {
        return in_array($status, ['black', 'white', 'suspicious'], true) ? $status : 'black';
    }

    private function healthSummary(): array
    {
        $guard = new RemoteServiceGuard();
        $users = new AdminUserRepository();
        return [
            'installLocked' => $users->hasAnyUsers(),
            'services' => [
                $guard->state('usom'),
                $guard->state('safe-browsing'),
                $guard->state('virustotal'),
            ],
        ];
    }

    private function audit(string $action, string $targetType, ?int $targetId, array $details = []): void
    {
        $user = (new AuthService())->user();
        $email = (string) ($user['email'] ?? 'bilinmiyor');
        (new AdminAuditLogRepository())->create($email, $action, $targetType, $targetId, $details);
    }
}

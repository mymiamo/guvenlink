<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Services\RateLimiter;
use App\Services\RequestAuditService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Router;

require dirname(__DIR__) . '/bootstrap.php';

$request = Request::capture();
$startedAt = microtime(true);

if ($request->method === 'OPTIONS') {
    Response::json(['ok' => true]);
    exit;
}

$rateLimiter = new RateLimiter();
$authPaths = ['/api/auth/login', '/admin/login'];
$apiPaths = ['/api/check', '/api/report'];

if (in_array($request->path, $authPaths, true)) {
    $hit = $rateLimiter->hit('auth', $request->ip(), (int) RATE_LIMIT_AUTH_MAX, (int) RATE_LIMIT_AUTH_WINDOW);
    if (!$hit['allowed']) {
        header('Retry-After: ' . $hit['retryAfter']);
        if ($request->path === '/admin/login') {
            Response::html('Cok fazla giris denemesi. Lutfen daha sonra tekrar deneyin.', 429);
        } else {
            Response::json(['error' => 'Cok fazla giris denemesi. Daha sonra tekrar deneyin.'], 429);
        }
        exit;
    }
}

if (in_array($request->path, $apiPaths, true)) {
    $hit = $rateLimiter->hit('api', $request->ip() . '|' . $request->path, (int) RATE_LIMIT_API_MAX, (int) RATE_LIMIT_API_WINDOW);
    if (!$hit['allowed']) {
        header('Retry-After: ' . $hit['retryAfter']);
        Response::json(['error' => 'Cok fazla istek gonderildi.'], 429);
        exit;
    }
}

$router = new Router();
$api = new ApiController();
$admin = new AdminController();

$router->add('GET', '/', static function (): void {
    Response::redirect('/admin');
});

$router->add('GET', '/api/check', [$api, 'check']);
$router->add('GET', '/api/feed', [$api, 'feed']);
$router->add('GET', '/api/meta', static function () use ($api): void {
    $api->meta();
});
$router->add('POST', '/api/report', [$api, 'submitReport']);
$router->add('POST', '/api/auth/login', [$api, 'login']);
$router->add('GET', '/api/admin/entries', [$api, 'listEntries']);
$router->add('POST', '/api/admin/entries', [$api, 'createEntry']);
$router->add('PUT', '/api/admin/entries', [$api, 'updateEntry']);
$router->add('POST', '/api/admin/import/usom', static function () use ($api): void {
    $api->importUsom();
});

$router->add('GET', '/admin/install', static function () use ($admin): void {
    $admin->installForm();
});
$router->add('POST', '/admin/install', [$admin, 'install']);
$router->add('GET', '/admin/login', static function () use ($admin): void {
    $admin->loginForm();
});
$router->add('POST', '/admin/login', [$admin, 'login']);
$router->add('GET', '/admin/logout', static function () use ($admin): void {
    $admin->logout();
});
$router->add('GET', '/admin', [$admin, 'dashboard']);
$router->add('POST', '/admin/entries/save', [$admin, 'saveEntry']);
$router->add('POST', '/admin/reports/approve', [$admin, 'approveReport']);
$router->add('POST', '/admin/reports/review', [$admin, 'reviewReport']);
$router->add('POST', '/admin/reports/reject', [$admin, 'rejectReport']);
$router->add('POST', '/admin/import', [$admin, 'runImport']);

$router->dispatch($request);
(new RequestAuditService())->log($request, http_response_code(), $startedAt);

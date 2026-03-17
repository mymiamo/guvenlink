<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Support\Request;
use App\Support\Response;
use App\Support\Router;

require dirname(__DIR__) . '/bootstrap.php';

$request = Request::capture();

if ($request->method === 'OPTIONS') {
    Response::json(['ok' => true]);
    exit;
}

$router = new Router();
$api = new ApiController();
$admin = new AdminController();

$router->add('GET', '/', static function (Request $request): void {
    Response::redirect('/admin');
});

$router->add('GET', '/api/check', [$api, 'check']);
$router->add('GET', '/api/feed', [$api, 'feed']);
$router->add('GET', '/api/meta', static function (Request $request) use ($api): void {
    $api->meta();
});
$router->add('POST', '/api/auth/login', [$api, 'login']);
$router->add('GET', '/api/admin/entries', [$api, 'listEntries']);
$router->add('POST', '/api/admin/entries', [$api, 'createEntry']);
$router->add('PUT', '/api/admin/entries', [$api, 'updateEntry']);
$router->add('POST', '/api/admin/import/usom', static function (Request $request) use ($api): void {
    $api->importUsom();
});

$router->add('GET', '/admin/login', static function (Request $request) use ($admin): void {
    $admin->loginForm();
});
$router->add('POST', '/admin/login', [$admin, 'login']);
$router->add('GET', '/admin/logout', static function (Request $request) use ($admin): void {
    $admin->logout();
});
$router->add('GET', '/admin', [$admin, 'dashboard']);
$router->add('POST', '/admin/entries/save', [$admin, 'saveEntry']);
$router->add('POST', '/admin/import', static function (Request $request) use ($admin): void {
    $admin->runImport();
});

$router->dispatch($request);

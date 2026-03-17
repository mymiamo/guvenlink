<?php

declare(strict_types=1);

use App\Support\Logger;

define('BASE_PATH', __DIR__);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = BASE_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

require_once BASE_PATH . '/config.php';
date_default_timezone_set('Europe/Istanbul');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'guvenlik_admin');
    session_start();
}

set_exception_handler(static function (Throwable $exception): void {
    Logger::error('Unhandled exception', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

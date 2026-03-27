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

foreach ([BASE_PATH . '/storage', BASE_PATH . '/storage/logs', BASE_PATH . '/storage/cache'] as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
}

if (!function_exists('app_base_path')) {
    function app_base_path(): string
    {
        $basePath = defined('APP_BASE_PATH') ? trim((string) APP_BASE_PATH) : '';
        if ($basePath === '' || $basePath === '/') {
            return '';
        }

        return rtrim($basePath, '/');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = '/'): string
    {
        $normalizedPath = '/' . ltrim($path, '/');
        $basePath = app_base_path();
        if ($basePath === '') {
            return $normalizedPath;
        }

        return $normalizedPath === '/' ? $basePath . '/' : $basePath . $normalizedPath;
    }
}

if (!function_exists('app_is_debug')) {
    function app_is_debug(): bool
    {
        $environment = strtolower((string) (defined('APP_ENV') ? APP_ENV : 'production'));

        return in_array($environment, ['local', 'development', 'dev', 'testing'], true);
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'guvenlink_admin');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
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
    $payload = [
        'error' => 'Internal server error',
    ];

    if (app_is_debug()) {
        $payload['debug_message'] = $exception->getMessage();
        $payload['debug_file'] = $exception->getFile();
        $payload['debug_line'] = $exception->getLine();
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

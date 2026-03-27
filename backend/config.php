<?php

declare(strict_types=1);

$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

$defineConfig = static function (string $key, mixed $default) use ($localConfig): void {
    if (defined($key)) {
        return;
    }

    $value = $localConfig[$key] ?? $default;
    define($key, $value);
};

$defineConfig('APP_ENV', 'production');
$defineConfig('APP_URL', 'http://localhost:8000');
$defineConfig('APP_BASE_PATH', '');
$defineConfig('APP_KEY', 'change-this-secret-key');
$defineConfig('SESSION_NAME', 'guvenlink_admin');

$defineConfig('DB_HOST', 'localhost');
$defineConfig('DB_NAME', 'guvenlink');
$defineConfig('DB_USER', 'guvenlink_user');
$defineConfig('DB_PASS', '');
$defineConfig('DB_PORT', '3306');
$defineConfig('DB_CHARSET', 'utf8mb4');

$defineConfig('INSTALL_ALLOW_WEB_BOOTSTRAP', true);
$defineConfig('USOM_API_URL', 'https://www.usom.gov.tr/api/address/index');
$defineConfig('USOM_IMPORT_PAGE_SIZE', 1000);
$defineConfig('USOM_MAX_PAGES', 0);

$defineConfig('EXTENSION_FEED_PAGE_SIZE', 5000);
$defineConfig('SAFE_BROWSING_API_KEY', '');
$defineConfig('SAFE_BROWSING_CLIENT_ID', 'guvenlink');
$defineConfig('SAFE_BROWSING_CLIENT_VERSION', '1.1.0');
$defineConfig('VIRUSTOTAL_API_KEY', '');

$defineConfig('REMOTE_HTTP_TIMEOUT', 8);
$defineConfig('REMOTE_HTTP_RETRIES', 1);
$defineConfig('REMOTE_CIRCUIT_BREAKER_THRESHOLD', 3);
$defineConfig('REMOTE_CIRCUIT_BREAKER_TTL', 300);
$defineConfig('ANALYSIS_CACHE_TTL', 300);

$defineConfig('RATE_LIMIT_AUTH_WINDOW', 900);
$defineConfig('RATE_LIMIT_AUTH_MAX', 5);
$defineConfig('RATE_LIMIT_API_WINDOW', 300);
$defineConfig('RATE_LIMIT_API_MAX', 60);

<?php

declare(strict_types=1);

define('APP_ENV', 'production');
define('APP_URL', 'https://your-domain.example/guvenlink/backend/public');
define('APP_BASE_PATH', '/guvenlink/backend/public');
define('APP_KEY', 'replace-with-your-secret-key');
define('SESSION_NAME', 'guvenlik_admin');

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_PASSWORD', 'ChangeMe123!');

define('USOM_API_URL', 'https://www.usom.gov.tr/api/address/index');
define('USOM_IMPORT_PAGE_SIZE', 1000);
define('USOM_MAX_PAGES', 0);

define('EXTENSION_FEED_PAGE_SIZE', 5000);

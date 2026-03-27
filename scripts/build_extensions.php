<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sourceDir = $root . '/extension-source';
$targets = [
    'chromium' => $root . '/extension',
    'firefox' => $root . '/extension-firefox',
];

function ensureDirectory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function copySourceTree(string $sourceDir, string $targetDir): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($sourceDir) + 1);
        $relativePath = str_replace('\\', '/', $relativePath);
        $targetPath = $targetDir . '/' . $relativePath;

        if ($item->isDir()) {
            ensureDirectory($targetPath);
            continue;
        }

        ensureDirectory(dirname($targetPath));
        copy($item->getPathname(), $targetPath);
    }
}

foreach ($targets as $targetDir) {
    ensureDirectory($targetDir);
    copySourceTree($sourceDir, $targetDir);
}

$iconSet = ['16' => 'logo/16x16-logo.png', '32' => 'logo/32x32-logo.png', '48' => 'logo/48x48-logo.png', '128' => 'logo/128x128-logo.png'];
$firefoxGeckoSettings = [
    'id' => 'guvenlink@example.com',
    'data_collection_permissions' => [
        'required' => ['browsingActivity', 'websiteContent'],
    ],
];

$chromiumManifest = [
    'manifest_version' => 3,
    'name' => 'Güvenlink',
    'version' => '1.1.0',
    'description' => 'Baglantilari analiz eden acik kaynak guvenlik uzantisi.',
    'icons' => $iconSet,
    'permissions' => ['storage', 'tabs', 'alarms', 'webNavigation', 'contextMenus', 'notifications'],
    'host_permissions' => ['<all_urls>'],
    'background' => ['service_worker' => 'background.js'],
    'action' => [
        'default_title' => 'Güvenlink',
        'default_popup' => 'popup.html',
        'default_icon' => $iconSet,
    ],
    'options_page' => 'settings.html',
    'content_scripts' => [['matches' => ['<all_urls>'], 'css' => ['tooltip.css'], 'js' => ['shared.js', 'content-script.js'], 'run_at' => 'document_start']],
    'web_accessible_resources' => [[
        'resources' => ['warning.html', 'warning.js', 'styles.css', 'tooltip.css', 'settings.html', 'settings.js', 'logo/16x16-logo.png', 'logo/32x32-logo.png', 'logo/48x48-logo.png', 'logo/128x128-logo.png', 'logo/16x16-yes.png', 'logo/32x32-yes.png', 'logo/48x48-yes.png', 'logo/128x128-yes.png', 'logo/16x16-waite.png', 'logo/32x32-wait.png', 'logo/48x48-wait.png', 'logo/128x128-wait.png', 'logo/16x16-danger.png', 'logo/32x32-danger.png', 'logo/48x48-danger.png', 'logo/128x128-danger.png'],
        'matches' => ['<all_urls>'],
    ]],
];

$firefoxManifest = [
    'manifest_version' => 2,
    'name' => 'Güvenlink',
    'version' => '1.1.0',
    'description' => 'Baglantilari analiz eden acik kaynak guvenlik uzantisi.',
    'icons' => $iconSet,
    'permissions' => ['storage', 'tabs', 'alarms', 'webNavigation', 'contextMenus', 'notifications', '<all_urls>'],
    'background' => ['scripts' => ['shared.js', 'idb.js', 'background.js']],
    'browser_action' => [
        'default_title' => 'Güvenlink',
        'default_popup' => 'popup.html',
        'default_icon' => $iconSet,
    ],
    'options_ui' => ['page' => 'settings.html', 'browser_style' => false],
    'content_scripts' => [['matches' => ['<all_urls>'], 'css' => ['tooltip.css'], 'js' => ['shared.js', 'content-script.js'], 'run_at' => 'document_start']],
    'web_accessible_resources' => ['warning.html', 'warning.js', 'styles.css', 'tooltip.css', 'settings.html', 'settings.js', 'logo/16x16-logo.png', 'logo/32x32-logo.png', 'logo/48x48-logo.png', 'logo/128x128-logo.png', 'logo/16x16-yes.png', 'logo/32x32-yes.png', 'logo/48x48-yes.png', 'logo/128x128-yes.png', 'logo/16x16-waite.png', 'logo/32x32-wait.png', 'logo/48x48-wait.png', 'logo/128x128-wait.png', 'logo/16x16-danger.png', 'logo/32x32-danger.png', 'logo/48x48-danger.png', 'logo/128x128-danger.png'],
    'browser_specific_settings' => ['gecko' => $firefoxGeckoSettings],
];

file_put_contents($targets['chromium'] . '/manifest.json', json_encode($chromiumManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents($targets['firefox'] . '/manifest.json', json_encode($firefoxManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "Extension outputs rebuilt.\n";

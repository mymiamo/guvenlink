<?php

declare(strict_types=1);

require __DIR__ . '/../backend/bootstrap.php';

$importer = new \App\Services\UsomImporter();
$result = $importer->import();

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

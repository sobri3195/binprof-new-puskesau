<?php
declare(strict_types=1);

// Router for local PHP CLI server. Deployment only needs index.php and assets.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$file = is_string($path) ? realpath(__DIR__ . $path) : false;
if ($file && str_starts_with($file,__DIR__ . '/assets/') && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';

<?php
declare(strict_types=1);

ini_set('display_errors','0');
ini_set('zend.exception_ignore_args','1');

// In cPanel mode B, set this to the absolute private directory or set BINPROF_ROOT in PHP's environment.
$privateRoot = getenv('BINPROF_ROOT') ?: dirname(__DIR__);
try {
    $bootstrap = $privateRoot . '/app/bootstrap.php';
    if (!is_readable($bootstrap)) {
        throw new RuntimeException('Private bootstrap unavailable');
    }
    require $bootstrap;
    Binprof\Controllers\Web::run();
} catch (Throwable) {
    error_log('BINPROF could not start');
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Layanan sedang bermasalah. Hubungi administrator.';
}

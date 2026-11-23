<?php
declare(strict_types=1);

// In cPanel mode B, set this to the absolute private directory or set BINPROF_ROOT in PHP's environment.
$privateRoot = getenv('BINPROF_ROOT') ?: dirname(__DIR__);
require $privateRoot . '/app/bootstrap.php';

Binprof\Controllers\Web::run();

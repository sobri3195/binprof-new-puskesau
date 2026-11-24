<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cli/common.php';

cli(static function (): int {
    $date = $_SERVER['argv'][1] ?? '';
    if (!Binprof\Services\Records::date($date)) {
        throw new RuntimeException('Invalid test date');
    }
    $result = Binprof\Services\Jobs::run($date);
    return $result['state'] === 'ok' ? 0 : 1;
});

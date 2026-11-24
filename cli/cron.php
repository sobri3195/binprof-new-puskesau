<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

cli(static function (): int {
    $result = Binprof\Services\Jobs::run();
    echo json_encode($result,JSON_THROW_ON_ERROR) . PHP_EOL;
    return $result['failures'] > 0 ? 1 : 0;
});

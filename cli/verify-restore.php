<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

use Binprof\Services\RestoreVerification;

cli(static function (): int {
    $args = array_slice($_SERVER['argv'],1);
    $expected = null;
    if ($args) {
        if (count($args) !== 2 || $args[0] !== '--expect' || !is_file($args[1]) || !is_readable($args[1])) {
            Binprof\fail(422,'Gunakan php cli/verify-restore.php [--expect /path/manifest.json].');
        }
        try {
            $json = file_get_contents($args[1],false,null,0,32769);
            $expected = $json !== false && strlen($json) <= 32768 ? json_decode($json,true,16,JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $expected = null;
        }
        if (!is_array($expected)) {
            Binprof\fail(422,'Manifest cadangan tidak valid.');
        }
    }

    $actual = RestoreVerification::capture();
    if ($expected !== null) {
        RestoreVerification::compare($expected,$actual);
    }
    echo json_encode($actual,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    return 0;
});

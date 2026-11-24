<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';
umask(0077);
ini_set('display_errors','0');
ini_set('zend.exception_ignore_args','1');

function cli(callable $execute): never
{
    try {
        exit((int) $execute());
    } catch (Binprof\Problem $error) {
        fwrite(STDERR,$error->getMessage() . PHP_EOL);
    } catch (Throwable $error) {
        fwrite(STDERR,'Perintah gagal (' . get_class($error) . '). Periksa konfigurasi privat dan runtime.' . PHP_EOL);
    }
    exit(1);
}

/** Read secrets from a pipe or a protected, temporary standard-input file. */
function privateInput(): array
{
    $line = stream_get_contents(STDIN,16385);
    if (strlen($line) > 16384) {
        throw new RuntimeException('Input limit');
    }
    $input = json_decode($line,true,8,JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new RuntimeException('Invalid input');
    }
    return $input;
}

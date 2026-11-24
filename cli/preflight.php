<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

cli(static function (): int {
    $checks = ['PHP >=8.4'=>PHP_VERSION_ID >= 80400];
    foreach (['PDO','pdo_mysql','fileinfo','sodium','mbstring'] as $extension) {
        $checks[$extension] = extension_loaded($extension);
    }
    $config = Binprof\config();
    $root = realpath($_SERVER["argv"][1] ?? dirname(__DIR__) . '/public');
    $directories = [dirname(__DIR__) . '/app',dirname(__DIR__) . '/database',dirname(__DIR__) . '/config',Binprof\configPath(),
        $config['storage'],dirname($config['keys'])];
    foreach (array_slice($_SERVER['argv'],1) ?: [$root] as $webroot) {
        $resolved = realpath($webroot);
        $checks['Pemisahan ' . $webroot] = $resolved !== false;
        foreach ($directories as $directory) {
            $path = realpath($directory);
            $checks['Pemisahan ' . $webroot] = $checks['Pemisahan ' . $webroot] && $path !== false
                && $path !== $resolved && !str_starts_with($path,$resolved . '/');
        }
    }
    $checks['Batas upload PHP'] = ini_parse_quantity(ini_get('upload_max_filesize')) >= $config['upload_bytes']
        && ini_parse_quantity(ini_get('post_max_size')) > $config['upload_bytes'];
    $checks['Kebijakan HTTPS'] = $config['require_https'];
    $checks['Sesi dan berkas dapat ditulis'] = is_writable($config['storage'] . '/sessions') && is_writable($config['storage'] . '/documents');
    $checks['Ruang tersedia (minimal satu upload)'] = @disk_free_space($config['storage']) > $config['upload_bytes'] * 2;
    Binprof\crypto();
    $db = Binprof\db();
    $version = $db->one('SELECT VERSION() AS v')['v'];
    $checks['Skema migrasi 002'] = (bool) $db->one('SELECT version FROM schema_migrations WHERE version=2');
    echo 'PHP ' . PHP_VERSION . ' · Database ' . $version . PHP_EOL;
    foreach ($checks as $key=>$result) {
        echo ($result ? '[OK] ' : '[PERIKSA] ') . $key . PHP_EOL;
    }
    echo "Verifikasi TLS, kuota akun, HTTP dan jadwal cron pada host secara terpisah.\n";
    return in_array(false,$checks,true) ? 1 : 0;
});

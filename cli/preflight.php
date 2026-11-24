<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

use Binprof\Services\PrivateLayout;

cli(static function (): int {
    $checks = ['PHP >=8.4'=>PHP_VERSION_ID >= 80400];
    foreach (['PDO','pdo_mysql','fileinfo','sodium','mbstring','session'] as $extension) {
        $checks[$extension] = extension_loaded($extension);
    }

    try {
        $config = Binprof\config();
        $checks['Konfigurasi privat dapat dibaca'] = true;
    } catch (Throwable) {
        $config = null;
        $checks['Konfigurasi privat dapat dibaca'] = false;
    }

    $notes = [];
    $database = 'belum diperiksa';
    if ($config !== null) {
        $roots = PrivateLayout::webRoots(array_slice($_SERVER['argv'],1));
        $public = $roots[0];
        $checks['Front controller dan aset tersedia'] = is_file($public . '/index.php')
            && is_file($public . '/assets/app.css') && is_file($public . '/assets/map.js');
        foreach ($roots as $webRoot) {
            $problems = PrivateLayout::problems($webRoot,$config);
            $checks['Pemisahan ' . $webRoot] = !$problems;
            foreach ($problems as $problem) {
                $notes[] = $webRoot . ' — ' . $problem;
            }
        }
        $checks['Batas upload PHP'] = PHP_VERSION_ID >= 80400 && ini_get('file_uploads')
            && ini_parse_quantity(ini_get('upload_max_filesize')) >= $config['upload_bytes']
            && ini_parse_quantity(ini_get('post_max_size')) > $config['upload_bytes'];
        $checks['Kebijakan HTTPS'] = $config['require_https'] === true;
        $checks['Sesi dan dokumen dapat ditulis'] = is_dir($config['storage'] . '/sessions')
            && is_writable($config['storage'] . '/sessions') && is_dir($config['storage'] . '/documents')
            && is_writable($config['storage'] . '/documents');
        $checks['Ruang tersedia (minimal satu upload)'] = @disk_free_space($config['storage']) > $config['upload_bytes'] * 2;

        try {
            Binprof\crypto();
            $checks['Keyring dapat dibaca'] = true;
        } catch (Throwable) {
            $checks['Keyring dapat dibaca'] = false;
        }
        try {
            $db = Binprof\db();
            $database = $db->one('SELECT VERSION() AS v')['v'];
            $checks['Koneksi database'] = true;
            $checks['Skema migrasi 002'] = (bool) $db->one('SELECT version FROM schema_migrations WHERE version=2');
        } catch (Throwable) {
            $checks['Koneksi database'] ??= false;
            $checks['Skema migrasi 002'] = false;
        }
    }

    echo 'PHP ' . PHP_VERSION . ' · Database ' . $database . PHP_EOL;
    foreach ($checks as $key=>$result) {
        echo ($result ? '[OK] ' : '[PERIKSA] ') . $key . PHP_EOL;
    }
    foreach ($notes as $note) {
        echo '  - ' . $note . PHP_EOL;
    }
    echo "Verifikasi TLS, kuota, konfigurasi PHP/FPM dan jadwal cron pada host secara terpisah.\n";
    return in_array(false,$checks,true) ? 1 : 0;
});

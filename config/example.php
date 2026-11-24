<?php
return [
    'dsn' => 'mysql:host=localhost;dbname=ACCOUNT_binprof;charset=utf8mb4',
    'db_user' => 'ACCOUNT_app',
    'db_password' => '',
    'storage' => dirname(__DIR__) . '/storage',
    'keys' => dirname(__DIR__) . '/storage/keys.json',
    'timezone' => 'Asia/Jakarta',
    'base_path' => '',
    'clean_urls' => false,
    'require_https' => true,
    'upload_bytes' => 5 * 1024 * 1024,
    // Optional HTTPS tile template and plain-text attribution per provider's license.
    'tiles' => '',
    'tile_attribution' => '',
];

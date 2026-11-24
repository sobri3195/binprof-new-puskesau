<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

cli(static function (): int {
    $config = Binprof\config();
    $paths = [$config['storage'],$config['storage'] . '/sessions',$config['storage'] . '/documents',dirname($config['keys'])];
    foreach (array_unique($paths) as $path) {
        if (!is_dir($path) && !mkdir($path,0700,true)) {
            throw new RuntimeException('Runtime directory unavailable');
        }
    }
    if (!is_file($config['keys'])) {
        $handle = fopen($config['keys'],'xb');
        if (!$handle) {
            throw new RuntimeException('Keyring could not be initialized');
        }
        try {
            $encode = static fn()=>sodium_bin2base64(random_bytes(32),SODIUM_BASE64_VARIANT_ORIGINAL);
            $data = json_encode(['active'=>'v1','keys'=>['v1'=>$encode()],'digest'=>$encode()],JSON_THROW_ON_ERROR);
            if (fwrite($handle,$data) !== strlen($data)) {
                throw new RuntimeException('Keyring could not be written');
            }
            fflush($handle);
            fsync($handle);
        } finally {
            fclose($handle);
        }
    }
    Binprof\crypto();
    echo "Penyimpanan dan kunci tersedia.\n";
    return 0;
});

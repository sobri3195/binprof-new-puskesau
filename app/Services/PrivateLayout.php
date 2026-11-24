<?php
declare(strict_types=1);
namespace Binprof\Services;

use function Binprof\configPath;

final class PrivateLayout
{
    public static function webRoots(array $specified): array
    {
        return $specified ?: [dirname(__DIR__,2) . '/public'];
    }

    /** A missing path is allowed during initialization only after resolving its existing ancestors. */
    public static function problems(string $webRoot, array $config, bool $initializing = false): array
    {
        $root = realpath($webRoot);
        if ($root === false || !is_dir($root)) {
            return ['document root tidak ditemukan'];
        }

        $project = dirname(__DIR__,2);
        $storage = rtrim($config['storage'],'/');
        $paths = [
            'kode aplikasi'=>[$project . '/app',false],
            'dependensi ekspor'=>[$project . '/vendor-exports',false],
            'alat CLI'=>[$project . '/cli',false],
            'skema basis data'=>[$project . '/database',false],
            'direktori konfigurasi'=>[$project . '/config',false],
            'konfigurasi aktif'=>[configPath(),false],
            'penyimpanan'=>[$storage,$initializing],
            'sesi'=>[$storage . '/sessions',$initializing],
            'dokumen'=>[$storage . '/documents',$initializing],
            'keyring'=>[$config['keys'],$initializing],
        ];
        $problems = [];
        foreach ($paths as $label=>[$path,$allowMissing]) {
            $resolved = self::resolve($path,$allowMissing);
            if ($resolved === null) {
                $problems[] = $label . ' tidak dapat diresolusikan';
            } elseif (self::contains($root,$resolved)) {
                $problems[] = $label . ' berada di bawah document root';
            }
        }
        if (!$initializing && ini_get('file_uploads')) {
            $temp = realpath(ini_get('upload_tmp_dir') ?: sys_get_temp_dir());
            if ($temp === false || !is_dir($temp) || !is_writable($temp)) {
                $problems[] = 'direktori sementara unggahan tidak dapat ditulis';
            } elseif (self::contains($root,$temp)) {
                $problems[] = 'direktori sementara unggahan berada di bawah document root';
            }
        }
        return $problems;
    }

    private static function contains(string $root, string $path): bool
    {
        return $path === $root || str_starts_with($path,rtrim($root,'/') . '/');
    }

    private static function resolve(string $path, bool $allowMissing): ?string
    {
        if ($path === '') {
            return null;
        }
        $resolved = realpath($path);
        if ($resolved !== false) {
            return $resolved;
        }
        if (!$allowMissing || is_link($path) || file_exists($path)
            || in_array(basename($path),['.','..'],true)) {
            return null;
        }
        $parent = self::resolve(dirname($path),true);
        return $parent === null ? null : rtrim($parent,'/') . '/' . basename($path);
    }
}

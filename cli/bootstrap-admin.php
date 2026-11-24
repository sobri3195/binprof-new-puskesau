<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

use Binprof\Services\Auth;
use function Binprof\{audit,db,fail};

cli(static function (): int {
    $input = privateInput();
    $ids = db()->transaction(static function () use ($input): array {
        $flag = db()->one("SELECT value FROM app_settings WHERE name='bootstrap_done' FOR UPDATE");
        if (!$flag || $flag['value'] !== '0') {
            fail(409,'Bootstrap sudah dikunci.');
        }
        $ids = [];
        foreach (['admin','case_manager'] as $role) {
            $name = $input[$role]['username'] ?? '';
            $password = $input[$role]['password'] ?? '';
            if (!is_string($name) || !preg_match('/^[a-z0-9_.-]{3,64}$/D',$name) || !is_string($password)) {
                fail(422,'Gunakan dua akun awal yang berbeda dengan nama pengguna yang valid.');
            }
            db()->run('INSERT INTO users(username,password_hash,case_manager) VALUES (?,?,?)',
                [$name,Auth::hash($password),(int) ($role === 'case_manager')]);
            $id = db()->lastId();
            if ($role === 'admin') {
                db()->run("INSERT INTO user_roles VALUES (?,'admin')",[$id]);
            }
            audit($id,'bootstrap.' . $role,$id);
            $ids[] = $id;
        }
        db()->run("UPDATE app_settings SET value='1' WHERE name='bootstrap_done'");
        return $ids;
    });
    echo "Bootstrap selesai. ID administrator {$ids[0]}, ID pejabat penugasan {$ids[1]}.\n";
    return 0;
});

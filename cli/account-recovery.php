<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

use Binprof\Services\Auth;
use function Binprof\{audit,db,fail,id};

cli(static function (): int {
    $input = privateInput();
    $target = id($input['id'] ?? '');
    $action = $input['action'] ?? '';
    db()->transaction(static function () use ($input,$target,$action): void {
        db()->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$target]) ?? fail(404,'Akun tidak ditemukan.');
        if ($action === 'reset' && is_string($input['password'] ?? null)) {
            db()->run('UPDATE users SET password_hash=?,session_version=session_version+1,active=1 WHERE id=?',
                [Auth::hash($input['password']),$target]);
        } elseif ($action === 'case-capabilities' && is_bool($input['create'] ?? null) && is_bool($input['cross_unit'] ?? null)) {
            db()->run('UPDATE users SET case_creator=?,case_cross_unit=?,session_version=session_version+1 WHERE id=?',
                [(int) $input['create'],(int) $input['cross_unit'],$target]);
        } else {
            fail(422,'Tindakan CLI tidak valid.');
        }
        audit(null,'account.cli.' . $action,$target);
    });
    echo "Akun diperbarui.\n";
    return 0;
});

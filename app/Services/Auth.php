<?php
declare(strict_types=1);
namespace Binprof\Services;

use Binprof\Policies\Access;
use function Binprof\{audit,crypto,db,fail};

final class Auth
{
    public static function current(): ?Access
    {
        if (!isset($_SESSION['user'])) {
            return null;
        }
        $now = time();
        $user = Access::load((int) $_SESSION['user']);
        if (!$user || $user->account['session_version'] !== $_SESSION['version']
            || $now - $_SESSION['last'] > 1800 || $now - $_SESSION['started'] > 28800) {
            self::logout();
            return null;
        }
        $_SESSION['last'] = $now;
        return $user;
    }

    public static function login(mixed $name, mixed $password, string $ip): void
    {
        $name = is_string($name) ? strtolower(trim(substr($name, 0, 64))) : '';
        $password = is_string($password) && strlen($password) <= 72 ? $password : '';
        $keys = [crypto()->identifier(0, 'ip:' . $ip), crypto()->identifier(0, 'ip-user:' . $ip . ':' . $name)];
        $limited = db()->transaction(static function () use ($keys): bool {
            $limited = false;
            foreach ($keys as $index => $key) {
                db()->run('INSERT INTO login_limits VALUES (?,1,UTC_TIMESTAMP()+INTERVAL 15 MINUTE)
                    ON DUPLICATE KEY UPDATE attempts=IF(expires_at<=UTC_TIMESTAMP(),1,LEAST(65535,attempts+1)),
                    expires_at=IF(expires_at<=UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 15 MINUTE,expires_at)', [$key]);
                $attempts = (int) db()->one('SELECT attempts FROM login_limits WHERE bucket=?', [$key])['attempts'];
                $limited = $limited || $attempts > ($index === 0 ? 40 : 8);
            }
            return $limited;
        });
        if ($limited) {
            fail(429, 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
        }
        $account = db()->one('SELECT * FROM users WHERE username=?', [$name]);
        $dummy = '$2y$12$Q5ZcnrrWYyUrteQA63d7qOxbkLelC.9zDNJS.VZOT/7Kpt9XYi45O';
        $valid = !str_contains($password, "\0") && password_verify($password, $account['password_hash'] ?? $dummy);
        if (!$valid || !$account || !$account['active']) {
            fail(401, 'Nama pengguna atau kata sandi tidak valid.');
        }
        if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
            db()->run('UPDATE users SET password_hash=? WHERE id=? AND password_hash=?',
                [password_hash($password, PASSWORD_DEFAULT), (int) $account['id'], $account['password_hash']]);
        }
        session_regenerate_id(true);
        $_SESSION = ['user'=>(int) $account['id'], 'version'=>$account['session_version'], 'last'=>time(), 'started'=>time()];
        db()->run('DELETE FROM login_limits WHERE bucket=?', [$keys[1]]);
        audit((int) $account['id'], 'login');
    }

    public static function changePassword(Access $user, mixed $old, mixed $new): void
    {
        if (!is_string($old) || strlen($old) > 72 || str_contains($old,"\0") || !is_string($new)) {
            fail(422,'Masukkan kata sandi lama dan baru.');
        }
        db()->transaction(static function () use ($user,$old,$new): void {
            $row = db()->one('SELECT password_hash,active,session_version FROM users WHERE id=? FOR UPDATE',[$user->id]);
            if (!$row || !$row['active'] || $row['session_version'] !== $user->account['session_version']
                || !password_verify($old,$row['password_hash'])) {
                fail(403,'Kata sandi saat ini tidak valid.');
            }
            db()->run('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?',
                [self::hash($new),$user->id]);
            audit($user->id,'account.password');
        });
        self::logout();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function hash(string $password): string
    {
        if (strlen($password) < 14 || strlen($password) > 72 || str_contains($password, "\0")) {
            fail(422, 'Gunakan kata sandi 14–72 byte.');
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

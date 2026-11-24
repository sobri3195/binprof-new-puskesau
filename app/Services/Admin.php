<?php
declare(strict_types=1);
namespace Binprof\Services;

use Binprof\Policies\Access;
use function Binprof\{audit,db,fail,id};

final class Admin
{
    public const MASTER = [
        'unit'=>['units','Satuan'], 'category'=>['personnel_categories','Kategori'],
        'identifier'=>['identifier_types','Jenis nomor kedinasan'], 'facility'=>['facilities','Fasilitas'],
        'rank'=>['reference_values','Pangkat'],'grade'=>['reference_values','Golongan'],
        'corps'=>['reference_values','Korps'],'profession'=>['reference_values','Profesi'],
        'dikum'=>['reference_values','Dikum'],'dikmil'=>['reference_values','Dikmil'],'award'=>['reference_values','Penghargaan'],
    ];
    public const CASE_CAPS = ['read','write','submit','verify','download','export'];

    public static function master(Access $actor, string $kind, int $id, array $input): void
    {
        [$table] = self::MASTER[$kind] ?? fail(404,'Master tidak ditemukan.');
        db()->transaction(static function () use ($actor,$kind,$id,$input,$table): void {
            (Access::load($actor->id,true) ?? fail(403,'Akun tidak aktif.'))->require('admin');
            // Serialize hierarchy and category changes across administrators.
            db()->one("SELECT value FROM app_settings WHERE name='bootstrap_done' FOR UPDATE");
            $existing = $id ? db()->one("SELECT * FROM $table WHERE id=?" .
                ($table === 'reference_values' ? ' AND kind=?' : ''), $table === 'reference_values' ? [$id,$kind] : [$id]) : null;
            if ($id && !$existing) {
                fail(404,'Master tidak ditemukan.');
            }
            $code = strtoupper(Records::text($input['code'] ?? '',30) ?? '');
            $name = Records::text($input['name'] ?? '',160);
            if (!preg_match('/^[A-Z0-9_-]{1,30}$/D',$code) || !$name) {
                fail(422,'Kode dan nama master wajib valid.');
            }
            if ($existing && $code !== $existing['code']) {
                fail(422,'Kode master tetap setelah dibuat.');
            }
            $values = ['code'=>$code,'name'=>$name,'active'=>(int) (($input['active'] ?? '') === '1')];
            if ($kind === 'unit') {
                $parentInput = $input['parent_id'] ?? '';
                $parent = $parentInput === '' ? 0 : (id($parentInput) ?: fail(422,'Satuan induk tidak valid.'));
                $visited = [$id=>true];
                for ($ancestor = $parent; $ancestor;) {
                    if (isset($visited[$ancestor])) {
                        fail(422,'Hierarki satuan tidak boleh bersiklus.');
                    }
                    $visited[$ancestor] = true;
                    $row = db()->one('SELECT parent_id FROM units WHERE id=?', [$ancestor]) ?? fail(422,'Satuan induk tidak ditemukan.');
                    $ancestor = (int) $row['parent_id'];
                }
                $values['parent_id'] = $parent ?: null;
                $lat = Records::text($input['latitude'] ?? '',12);
                $lon = Records::text($input['longitude'] ?? '',12);
                if (($lat === null) !== ($lon === null) || ($lat !== null
                    && (!is_numeric($lat) || !is_numeric($lon) || abs((float) $lat) > 90 || abs((float) $lon) > 180))) {
                    fail(422,'Masukkan pasangan koordinat yang valid atau kosongkan keduanya.');
                }
                $values['latitude'] = $lat;
                $values['longitude'] = $lon;
            } elseif ($kind === 'category') {
                $type = id($input['identifier_type_id'] ?? '');
                if (!db()->one('SELECT 1 FROM identifier_types WHERE id=? AND active=1', [$type])) {
                    fail(422,'Pilih jenis identitas aktif.');
                }
                if ($existing && $type !== (int) $existing['identifier_type_id']
                    && db()->one('SELECT 1 FROM personnel WHERE category_id=? LIMIT 1', [$id])) {
                    fail(422,'Jenis identitas kategori yang telah digunakan tetap.');
                }
                $values['identifier_type_id'] = $type;
                $values['dikmil'] = (int) (($input['dikmil'] ?? '') === '1');
            } elseif ($kind === 'facility') {
                $unit = id($input['unit_id'] ?? '');
                if (!db()->one('SELECT 1 FROM units WHERE id=? AND active=1',[$unit]) || ($existing && $unit !== (int) $existing['unit_id'])) {
                    fail(422,'Pilih satuan aktif. Satuan fasilitas tetap setelah dibuat.');
                }
                $values['unit_id'] = $unit;
            } elseif ($table === 'reference_values') {
                $values['kind'] = $kind;
            }
            if ($id) {
                db()->run("UPDATE $table SET " . implode('=?,',array_keys($values)) . '=? WHERE id=?', [...array_values($values),$id]);
            } else {
                db()->run("INSERT INTO $table(" . implode(',',array_keys($values)) . ') VALUES (' .
                    implode(',',array_fill(0,count($values),'?')) . ')', array_values($values));
                $id = db()->lastId();
            }
            audit($actor->id,'master.' . $kind,$id);
        });
    }

    public static function account(Access $actor, int $target, array $input): void
    {
        if ($target === $actor->id) {
            fail(403,'Perubahan akses sendiri memerlukan administrator lain.');
        }
        db()->transaction(static function () use ($actor,$target,$input): void {
            $ids = $target ? [$target,$actor->id] : [$actor->id];
            sort($ids,SORT_NUMERIC);
            foreach ($ids as $id) {
                $row = db()->one('SELECT id FROM users WHERE id=? FOR UPDATE', [$id]) ?? fail(404,'Akun tidak ditemukan.');
            }
            (Access::load($actor->id,true) ?? fail(403,'Akun tidak aktif.'))->require('admin');
            $name = strtolower(Records::text($input['username'] ?? '',64) ?? '');
            $password = $input['password'] ?? '';
            if (!preg_match('/^[a-z0-9_.-]{3,64}$/D',$name) || !is_string($password) || (!$target && $password === '')) {
                fail(422,'Nama pengguna atau kata sandi awal tidak valid.');
            }
            $hash = $password === '' ? null : Auth::hash($password);
            $active = (int) (($input['active'] ?? '') === '1');
            if ($target) {
                $currentTarget = db()->one('SELECT username,case_manager,case_creator,case_cross_unit FROM users WHERE id=?',[$target]);
                if (($hash !== null || $name !== $currentTarget['username']) && ($currentTarget['case_manager']
                    || $currentTarget['case_creator'] || $currentTarget['case_cross_unit']
                    || db()->one('SELECT 1 FROM case_access_grants WHERE user_id=? LIMIT 1',[$target]))) {
                    fail(403,'Perubahan identitas login akun kasus memerlukan prosedur CLI.');
                }
                db()->run('UPDATE users SET username=?,password_hash=COALESCE(?,password_hash),active=?,session_version=session_version+1 WHERE id=?',
                    [$name,$hash,$active,$target]);
            } else {
                db()->run('INSERT INTO users(username,password_hash,active) VALUES (?,?,?)', [$name,$hash,$active]);
                $target = db()->lastId();
            }
            $roles = $input['roles'] ?? [];
            $units = $input['units'] ?? [];
            $modules = $input['modules'] ?? [];
            if (!is_array($roles) || !is_array($units) || !is_array($modules) || count($modules) > 6 || count($roles) > 6 || count($units) > 1000) {
                fail(422,'Periksa peran dan cakupan.');
            }
            db()->run('DELETE FROM user_roles WHERE user_id=?', [$target]);
            db()->run('DELETE FROM user_unit_scopes WHERE user_id=?', [$target]);
            $allowedRoles = array_column(db()->all('SELECT code FROM roles'),'code');
            foreach ($roles as $role) {
                if (!in_array($role,$allowedRoles,true)) {
                    fail(422,'Peran tidak valid.');
                }
                db()->run('INSERT INTO user_roles VALUES (?,?)', [$target,$role]);
            }
            db()->run('DELETE FROM user_verification_modules WHERE user_id=?',[$target]);
            foreach ($modules as $module) {
                if (!is_string($module) || $module === 'case' || !isset(Records::LABELS[$module])) {
                    fail(422,'Modul verifikasi tidak valid.');
                }
                db()->run('INSERT INTO user_verification_modules VALUES (?,?)',[$target,$module]);
            }
            foreach ($units as $unit) {
                db()->run('INSERT INTO user_unit_scopes VALUES (?,?)', [$target,id($unit)]);
            }
            audit($actor->id,'account.update',$target);
        });
    }

    public static function grant(Access $actor, int $target, int $case, string $capability, bool $give, ?string $expiry): void
    {
        if ($actor->id === $target || !in_array($capability,self::CASE_CAPS,true)
            || ($expiry && !Records::date($expiry))) {
            fail(422,'Penugasan tidak valid. Penugasan diri sendiri dilarang.');
        }
        db()->transaction(static function () use ($actor,$target,$case,$capability,$give,$expiry): void {
            $actors = [$actor->id,$target];
            sort($actors,SORT_NUMERIC);
            foreach ($actors as $id) {
                db()->one('SELECT id FROM users WHERE id=? FOR UPDATE', [$id]) ?? fail(404,'Akun tidak ditemukan.');
            }
            $current = Access::load($actor->id,true);
            if (!$current || !$current->account['case_manager']) {
                fail(403,'Kewenangan pengelola penugasan kasus diperlukan.');
            }
            if (!db()->one('SELECT id FROM disciplinary_cases WHERE id=?', [$case])) {
                fail(404,'Catatan tidak ditemukan.');
            }
            if ($give) {
                db()->run('INSERT INTO case_access_grants VALUES (?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by),expires_on=VALUES(expires_on)',
                    [$case,$target,$capability,$actor->id,$expiry]);
            } else {
                db()->run('DELETE FROM case_access_grants WHERE case_id=? AND user_id=? AND capability=?', [$case,$target,$capability]);
            }
            audit($actor->id,'case.grant.' . ($give ? 'give' : 'revoke'),$case);
        });
    }
}

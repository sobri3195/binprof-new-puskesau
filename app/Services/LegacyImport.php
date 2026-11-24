<?php
declare(strict_types=1);
namespace Binprof\Services;

use Binprof\Policies\Access;
use Binprof\Problem;
use function Binprof\{audit,crypto,db,fail,id};

final class LegacyImport
{
    public const MODULES = ['profile'=>'Personel baru','dikum'=>'Dikum','dikmil'=>'Dikmil','award'=>'Penghargaan'];
    private const MAX_ROWS = 100;
    private const MAX_BYTES = 131072;
    private const LIFETIME = 600;

    public static function fields(string $module): array
    {
        if (!isset(self::MODULES[$module])) {
            fail(422,'Pilih modul impor yang valid.');
        }
        return ($module === 'profile' ? [] : ['person_id'=>['ID personel terverifikasi','number',true]])
            + Records::fields($module);
    }

    public static function load(Access $user): ?array
    {
        if (!isset($_SESSION['import_preview'])) {
            return null;
        }
        $stage = crypto()->open($_SESSION['import_preview'],'import:' . $user->id);
        if ($stage['expires'] <= time() || $stage['session_version'] !== $user->account['session_version']) {
            unset($_SESSION['import_preview']);
            return null;
        }
        return $stage;
    }

    public static function preview(Access $user, array $input, array $upload): array
    {
        $user->require('write');
        unset($_SESSION['import_preview']);
        $module = is_string($input['module'] ?? null) ? $input['module'] : '';
        $fields = self::fields($module);
        $unit = id($input['unit_id'] ?? '');
        self::requireUnit($user,$unit);
        $delimiter = match ($input['separator'] ?? '') {
            'comma'=>',','semicolon'=>';',default=>fail(422,'Pilih pemisah CSV.'),
        };
        $file = $upload['tmp_name'] ?? '';
        $size = is_string($file) && is_uploaded_file($file) ? filesize($file) : false;
        if (($upload['error'] ?? null) !== UPLOAD_ERR_OK || !$size || $size > self::MAX_BYTES
            || !is_string($upload['name'] ?? null) || !str_ends_with(strtolower($upload['name']),'.csv')) {
            fail(422,'Pilih CSV UTF-8 dengan ukuran maksimal 128 KiB.');
        }

        $stream = fopen($file,'rb');
        if (!$stream) {
            fail(422,'CSV tidak dapat dibaca.');
        }
        try {
            $contents = stream_get_contents($stream);
            if ($contents === false || !mb_check_encoding($contents,'UTF-8') || str_contains($contents,"\0")) {
                fail(422,'CSV harus menggunakan UTF-8.');
            }
            rewind($stream);
            if (fread($stream,3) !== "\xEF\xBB\xBF") {
                rewind($stream);
            }
            $header = fgetcsv($stream,0,$delimiter,'"','');
            if (!$header || count($header) > 64) {
                fail(422,'CSV harus memiliki satu baris judul dan maksimal 64 kolom.');
            }
            $header = array_map(static fn($value)=>Records::text($value,100),$header);
            if (in_array(null,$header,true) || count($header) !== count(array_unique($header))) {
                fail(422,'Judul kolom CSV harus terisi dan unik.');
            }
            $source = $input['mapping'] ?? [];
            if (!is_array($source)) {
                fail(422,'Pemetaan kolom tidak valid.');
            }
            $mapping = [];
            foreach ($fields as $key=>$field) {
                $column = Records::text($source[$key] ?? null,100);
                if ($column !== null) {
                    $index = array_search($column,$header,true);
                    if ($index === false || in_array($index,$mapping,true)) {
                        fail(422,'Pilih judul yang ada pada CSV dan gunakan satu judul per field.');
                    }
                    $mapping[$key] = $index;
                } elseif ($field[2] ?? false) {
                    fail(422,'Petakan kolom wajib ' . $field[0] . '.');
                }
            }
            $errors = [];
            $seen = self::pending($user,$unit,$module);
            $rows = [];
            $number = 1;
            while (($raw = fgetcsv($stream,0,$delimiter,'"','')) !== false) {
                if ($raw === [null]) {
                    continue;
                }
                if (++$number > self::MAX_ROWS + 1) {
                    fail(422,'Maksimal 100 baris data per impor.');
                }
                try {
                    if (count($raw) !== count($header)) {
                        fail(422,'Jumlah kolom berbeda dari judul.');
                    }
                    $record = [];
                    foreach ($mapping as $key=>$column) {
                        $value = $raw[$column];
                        if (is_string($value) && preg_match('/^(?:[\s\p{Cf}]*[=+@-]|[\t\r\n])/u',$value)) {
                            fail(422,'Nilai yang mirip formula perlu diperbaiki.');
                        }
                        $record[$key] = $value;
                    }
                    self::validate($user,$unit,$module,$record,$seen);
                    $rows[] = ['number'=>$number,'values'=>$record];
                } catch (Problem $error) {
                    $errors[$number] = $error->getMessage();
                }
            }
        } finally {
            fclose($stream);
        }
        if (!$rows && !$errors) {
            fail(422,'CSV tidak memuat baris data.');
        }

        $token = bin2hex(random_bytes(32));
        if (!$errors) {
            $_SESSION['import_preview'] = crypto()->seal([
                'token'=>$token,'unit'=>$unit,'module'=>$module,'rows'=>$rows,
                'session_version'=>$user->account['session_version'],'expires'=>time()+self::LIFETIME,
            ],'import:' . $user->id);
        }
        return compact('rows','errors','token');
    }

    public static function commit(Access $user, mixed $token): int
    {
        $user->require('write');
        $stage = self::load($user);
        if (!$stage || !is_string($token) || !hash_equals($stage['token'],$token)) {
            fail(409,'Pratinjau kedaluwarsa. Periksa ulang CSV.');
        }
        $count = db()->transaction(static function () use ($user,$stage,$token): int {
            $actor = Access::load($user->id,true) ?? fail(403,'Akun tidak aktif.');
            $actor->require('write');
            if ($actor->account['session_version'] !== $stage['session_version']) {
                fail(403,'Silakan masuk kembali.');
            }
            if ($receipt = db()->one('SELECT draft_count FROM import_batches WHERE token=? AND author_id=?',[$token,$actor->id])) {
                return (int) $receipt['draft_count'];
            }
            self::requireUnit($actor,$stage['unit']);
            $seen = self::pending($actor,$stage['unit'],$stage['module']);
            foreach ($stage['rows'] as $line) {
                $record = $line['values'];
                try {
                    $person = self::validate($actor,$stage['unit'],$stage['module'],$record,$seen,true);
                    Changes::create($actor,$stage['module'],$person,$stage['unit'],0,$record);
                } catch (Problem $error) {
                    fail(409,'Baris ' . $line['number'] . ' perlu dipratinjau ulang. ' . $error->getMessage());
                }
            }
            $count = count($stage['rows']);
            db()->run('INSERT INTO import_batches(token,author_id,draft_count) VALUES (?,?,?)',[$token,$actor->id,$count]);
            audit($actor->id,'import.drafts',$stage['unit']);
            return $count;
        });
        unset($_SESSION['import_preview']);
        return $count;
    }

    private static function requireUnit(Access $user, int $unit): void
    {
        if (!$unit || !$user->unit($unit) || !db()->one('SELECT 1 FROM units WHERE id=? AND active=1',[$unit])) {
            fail(422,'Pilih satuan aktif dalam cakupan Anda.');
        }
    }

    private static function pending(Access $user, int $unit, string $module): array
    {
        $rows = db()->all("SELECT q.id,q.revision,q.personnel_id,r.payload_cipher
            FROM change_requests q JOIN change_request_revisions r ON r.request_id=q.id AND r.revision=q.revision
            LEFT JOIN personnel p ON p.id=q.personnel_id
            WHERE q.owner_id=? AND q.unit_id=? AND q.module=? AND q.status IN ('draft','submitted','returned')
              AND q.education_id IS NULL AND q.award_id IS NULL
              AND ((?='profile' AND q.personnel_id IS NULL) OR p.unit_id=?)",
            [$user->id,$unit,$module,$module,$unit]);
        $seen = [];
        $required = array_keys(array_filter(Records::fields($module),static fn($field)=>$field[2] ?? false));
        foreach ($rows as $row) {
            $data = crypto()->open($row['payload_cipher'],Changes::context($row));
            if (array_diff($required,array_keys(array_filter($data,static fn($value)=>$value !== null)))) {
                continue;
            }
            $key = self::key($module,(int) $row['personnel_id'],$data);
            $seen[self::fingerprint($module,$key)] = true;
        }
        return $seen;
    }

    private static function validate(Access $user, int $unit, string $module, array $record, array &$seen, bool $lock = false): int
    {
        $personId = $module === 'profile' ? 0 : id($record['person_id'] ?? '');
        $person = $module === 'profile' ? null : $user->person($personId,$lock);
        if ($person && (int) $person['unit_id'] !== $unit) {
            fail(422,'Personel tidak ditemukan pada satuan ini.');
        }
        $data = Records::normalize($module,$record);
        Records::validate($module,$data,$person,$user);
        $key = self::key($module,$personId,$data);
        $query = match ($module) {
            'profile'=>'SELECT 1 FROM personnel_identifiers i JOIN personnel p ON p.id=i.personnel_id
                JOIN user_unit_scopes s ON s.unit_id=p.unit_id
                WHERE i.type_id=? AND i.digest=? AND s.user_id=?',
            'award'=>'SELECT 1 FROM personnel_awards WHERE personnel_id=? AND type_id=? AND awarded_on=? AND issuer=? LIMIT 1',
            default=>'SELECT 1 FROM education_histories WHERE personnel_id=? AND kind=? AND type_id=?
                AND institution=? AND program <=> ? AND start_on <=> ? AND start_year <=> ? AND end_on <=> ? AND end_year <=> ? LIMIT 1',
        };
        $fingerprint = self::fingerprint($module,$key);
        $existing = db()->one($query,$module === 'profile' ? [...$key,$user->id] : $key);
        if ($existing || isset($seen[$fingerprint])) {
            fail(422,'Data berpotensi duplikat. Periksa data terbit, pengajuan Anda, dan baris sebelumnya.');
        }
        $seen[$fingerprint] = true;
        return $personId;
    }

    private static function key(string $module, int $person, array $data): array
    {
        if ($module === 'profile') {
            $type = (int) db()->one('SELECT identifier_type_id FROM personnel_categories WHERE id=?',
                [$data['category_id']])['identifier_type_id'];
            return [$type,crypto()->identifier($type,$data['identifier'])];
        }
        return $module === 'award' ? [$person,(int) $data['type_id'],$data['awarded_on'],$data['issuer']]
            : [$person,$module,(int) $data['type_id'],$data['institution'],$data['program'],
                $data['start_on'],$data['start_year'] === null ? null : (int) $data['start_year'],
                $data['end_on'],$data['end_year'] === null ? null : (int) $data['end_year']];
    }

    private static function fingerprint(string $module, array $key): string
    {
        if ($module !== 'profile') {
            $key = array_map(static fn($value)=>is_string($value) ? mb_strtolower($value) : $value,$key);
        }
        return hash('sha256',serialize($key));
    }
}

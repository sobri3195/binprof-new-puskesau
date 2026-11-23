<?php
declare(strict_types=1);
namespace Binprof\Services;

use Binprof\Policies\Access;
use function Binprof\{crypto,db,fail,id,today};

final class Records
{
    public const LABELS = [
        'profile'=>'Identitas dan profil', 'dikum'=>'Pendidikan umum', 'dikmil'=>'Pendidikan militer',
        'award'=>'Penghargaan', 'retirement'=>'Perkiraan pensiun', 'service'=>'Mutasi, status dan arsip', 'case'=>'Kasus',
    ];

    public const STATUS = ['active'=>'Aktif','inactive'=>'Nonaktif','retired'=>'Pensiun','archived'=>'Arsip'];

    public static function fields(string $module): array
    {
        return match ($module) {
            'profile'=>[
                'name'=>['Nama','text',true], 'category_id'=>['Kategori','category',true],
                'identifier'=>['NRP / NIP / nomor kedinasan','text',true], 'birth_date'=>['Tanggal lahir','date'],
                'rank_id'=>['Pangkat','rank'], 'grade_id'=>['Golongan ruang','grade'], 'corps_id'=>['Korps','corps'],
                'profession_id'=>['Profesi','profession'], 'specialty'=>['Spesialisasi','text'],
            ],
            'service'=>[
                'unit_id'=>['Satuan tujuan','unit',true], 'facility_id'=>['Fasilitas','facility'],
                'position_title'=>['Jabatan','text'], 'service_status'=>['Status dinas',self::STATUS,true],
                'effective_on'=>['Tanggal efektif','date',true], 'reason'=>['Alasan perubahan','textarea',true],
            ],
            'dikum','dikmil'=>[
                'type_id'=>['Jenis / jenjang',$module,true], 'institution'=>['Institusi / lembaga','text',true],
                'program'=>['Program studi','text'], 'cohort'=>['Angkatan','text'],
                'start_on'=>['Tanggal mulai','date'], 'start_year'=>['Tahun mulai (jika tanggal tidak diketahui)','year'],
                'end_on'=>['Tanggal selesai','date'], 'end_year'=>['Tahun selesai (jika tanggal tidak diketahui)','year'],
                'status'=>['Status',['ongoing'=>'Berlangsung','completed'=>'Selesai','not_applicable'=>'Tidak berlaku'],true],
                'outcome'=>['Hasil','text'], 'certificate'=>['Nomor ijazah / sertifikat','text'],
            ],
            'award'=>[
                'type_id'=>['Jenis penghargaan','award',true], 'issuer'=>['Pemberi','text',true],
                'level_name'=>['Tingkat','text'], 'awarded_on'=>['Tanggal','date',true],
                'decision_number'=>['Nomor keputusan','text'], 'description'=>['Uraian','textarea'],
            ],
            'retirement'=>['estimate_date'=>['Tanggal perkiraan','date',true], 'source'=>['Sumber atau alasan','textarea',true]],
            'case'=>[
                'category'=>['Kategori',['discipline'=>'Disiplin','violation'=>'Pelanggaran','legal'=>'Perkara hukum'],true],
                'number'=>['Nomor referensi','text',true], 'reported_on'=>['Tanggal laporan','date',true],
                'title'=>['Judul','text',true], 'officer'=>['Penanggung jawab','text',true],
                'stage'=>['Tahap',['report'=>'Laporan','review'=>'Pemeriksaan','decision'=>'Keputusan','closed'=>'Selesai'],true],
                'parties'=>['Pihak (satu per baris, ID personel: peran)','textarea',true],
                'progress'=>['Perkembangan','textarea'], 'decision'=>['Hasil / keputusan','textarea'],
                'source'=>['Sumber keputusan','textarea'],
            ],
            default=>fail(404, 'Modul tidak ditemukan.'),
        };
    }

    public static function text(mixed $value, int $limit): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('//u', $value) || str_contains($value, "\0") || mb_strlen($value) > $limit) {
            fail(422, 'Masukan melebihi batas atau formatnya tidak valid.');
        }
        return trim($value) === '' ? null : trim($value);
    }

    public static function date(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)
            && (int) $parts[1] >= 1000 && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    public static function options(string $type, bool $activeOnly = true): array
    {
        $where = $activeOnly ? ' WHERE active=1' : '';
        return match ($type) {
            'category'=>db()->all('SELECT id,name FROM personnel_categories' . $where . ' ORDER BY name'),
            'unit'=>db()->all('SELECT id,name FROM units' . $where . ' ORDER BY name'),
            'facility'=>db()->all('SELECT id,name FROM facilities' . $where . ' ORDER BY name'),
            'identifier'=>db()->all('SELECT id,name FROM identifier_types' . $where . ' ORDER BY name'),
            'rank','grade','corps','profession','dikum','dikmil','award'=>
                db()->all('SELECT id,name FROM reference_values WHERE kind=?' . ($activeOnly ? ' AND active=1' : '') . ' ORDER BY name', [$type]),
            default=>[],
        };
    }

    public static function normalize(string $module, array $input): array
    {
        $data = [];
        foreach (self::fields($module) as $key => [$label,$type]) {
            $limit = match ($key) {
                'parties','progress','decision'=>4000, 'source','description','reason'=>500,
                'cohort'=>60, 'level_name'=>100, 'decision_number'=>120, default=>160,
            };
            $value = self::text($input[$key] ?? null, $limit);
            if ($value !== null) {
                $value = match (true) {
                    is_array($type) => array_key_exists($value, $type) ? $value : fail(422, $label . ' tidak valid.'),
                    $type === 'date' => self::date($value) ? $value : fail(422, $label . ' tidak valid.'),
                    $type === 'year' => preg_match('/^(18|19|20|21)\d{2}$/D', $value) ? (int) $value : fail(422, $label . ' tidak valid.'),
                    in_array($type, ['text','textarea'], true) => $value,
                    default => id($value) ?: fail(422, $label . ' tidak valid.'),
                };
            }
            $data[$key] = $value;
        }
        if (($data['identifier'] ?? null) !== null) {
            $data['identifier'] = strtoupper($data['identifier']);
            if (!preg_match('/^[A-Z0-9][A-Z0-9.\/-]{0,63}$/D', $data['identifier'])) {
                fail(422, 'Nomor kedinasan menggunakan huruf Latin, angka, titik, garis miring, atau tanda hubung.');
            }
        }
        return $data;
    }

    public static function validate(string $module, array $data, ?array $person, Access $author): void
    {
        foreach (self::fields($module) as $key => $field) {
            if (($field[2] ?? false) && $data[$key] === null) {
                fail(422, $field[0] . ' wajib diisi untuk pengajuan.');
            }
            $type = $field[1];
            if (is_string($type) && !in_array($type, ['text','textarea','year','date'], true) && $data[$key] !== null
                && !in_array($data[$key], array_column(self::options($type), 'id'))) {
                fail(422, $field[0] . ' tidak aktif atau tidak tersedia.');
            }
        }
        if ($module === 'profile' && $data['birth_date'] && $data['birth_date'] > today()) {
            fail(422, 'Tanggal lahir tidak dapat berada di masa depan.');
        }
        if ($module === 'service') {
            if ($data['facility_id'] && !db()->one('SELECT 1 FROM facilities WHERE id=? AND unit_id=?',
                [$data['facility_id'],$data['unit_id']])) {
                fail(422, 'Fasilitas harus berada di satuan tujuan.');
            }
            $start = db()->one('SELECT start_on FROM service_assignments WHERE open_personnel=?', [$person['id']])['start_on'];
            if ($data['effective_on'] < $start || ($data['service_status'] === 'archived' && $data['unit_id'] !== (int) $person['unit_id'])) {
                fail(422, 'Tanggal efektif atau satuan arsip tidak valid.');
            }
        }
        if (in_array($module, ['dikum','dikmil'], true)) {
            if ($module === 'dikmil' && !db()->one('SELECT 1 FROM personnel_categories WHERE id=? AND dikmil=1',
                [(int) $person['category_id']]) && $data['status'] !== 'not_applicable') {
                fail(422, 'Pendidikan militer belum berlaku pada kategori ini.');
            }
            $startYear = $data['start_on'] ? (int) substr($data['start_on'],0,4) : $data['start_year'];
            $endYear = $data['end_on'] ? (int) substr($data['end_on'],0,4) : $data['end_year'];
            if (($data['start_on'] && $data['start_year']) || ($data['end_on'] && $data['end_year'])
                || ($startYear && $endYear && $endYear < $startYear)
                || ($data['start_on'] && $data['end_on'] && $data['end_on'] < $data['start_on'])) {
                fail(422, 'Periode pendidikan tidak valid. Gunakan tanggal atau tahun pada setiap ujung periode.');
            }
        }
        if ($module === 'case') {
            if (in_array($data['stage'], ['decision','closed'], true) && (!$data['source'] || !$data['decision'])) {
                fail(422, 'Tahap keputusan dan selesai memerlukan hasil dan sumber.');
            }
            foreach (self::parties($data) as $personId => $role) {
                $party = db()->one('SELECT unit_id FROM personnel WHERE id=?', [$personId]);
                if (!$party || (!$author->account['case_cross_unit'] && !$author->unit((int) $party['unit_id']))) {
                    fail(422, 'Salah satu pihak tidak tersedia dalam cakupan penugasan.');
                }
            }
        }
    }

    public static function parties(array $data): array
    {
        $parties = [];
        foreach (preg_split('/\R/u', $data['parties'] ?? '', -1, PREG_SPLIT_NO_EMPTY) as $line) {
            [$number,$role] = array_pad(explode(':', $line, 2), 2, '');
            $person = id(trim($number));
            $role = self::text($role, 120);
            if (!$person || !$role || isset($parties[$person]) || count($parties) >= 50) {
                fail(422, 'Periksa format ID dan peran pihak. Maksimal 50 pihak unik.');
            }
            $parties[$person] = $role;
        }
        return $parties;
    }

    /** Object identity and current values; callers first check unit or case access. */
    public static function current(array $row, bool $lock = false): array
    {
        $module = $row['module'];
        $p = (int) ($row['personnel_id'] ?? 0);
        $c = (int) ($row['case_id'] ?? 0);
        $s = $lock ? ' FOR UPDATE' : '';
        $person = $p ? db()->one('SELECT * FROM personnel WHERE id=?' . $s, [$p]) : null;
        $object = match ($module) {
            'profile','service'=>$person,
            'dikum','dikmil'=>($row['education_id'] ?? null) ? db()->one('SELECT * FROM education_histories
                WHERE id=? AND personnel_id=? AND kind=?' . $s, [(int) $row['education_id'],$p,$module]) : null,
            'award'=>($row['award_id'] ?? null) ? db()->one('SELECT * FROM personnel_awards WHERE id=? AND personnel_id=?' . $s,
                [(int) $row['award_id'],$p]) : null,
            'retirement'=>db()->one('SELECT * FROM retirement_forecasts WHERE personnel_id=?' . $s, [$p]),
            'case'=>db()->one('SELECT * FROM disciplinary_cases WHERE id=?' . $s, [$c]),
        };
        if (($p && !$person) || (in_array($module, ['profile','service','case'], true) && ($p || $c) && !$object)
            || (($row['education_id'] ?? null) && !$object) || (($row['award_id'] ?? null) && !$object)) {
            fail(404, 'Catatan tidak ditemukan.');
        }
        $data = array_intersect_key($object ?? [], self::fields($module));
        if ($module === 'case' && !empty($object['content_cipher'])) {
            $data = crypto()->open($object['content_cipher'], 'case:' . $c);
        }
        if ($module === 'profile' && $person) {
            $identifier = db()->one('SELECT cipher FROM personnel_identifiers WHERE personnel_id=?', [$p]);
            $data['identifier'] = crypto()->open($identifier['cipher'], 'identifier:' . $p);
            $data['birth_date'] = $person['birth_cipher'] ? crypto()->open($person['birth_cipher'], 'birth:' . $p) : null;
        }
        if (in_array($module, ['dikum','dikmil'], true) && !empty($object['certificate_cipher'])) {
            $data['certificate'] = crypto()->open($object['certificate_cipher'], 'certificate:' . $object['id']);
        }
        if ($module === 'retirement') {
            $data['_basis'] = (int) ($person['version'] ?? 0);
        }
        return [(int) ($object['version'] ?? 0),$data,$person];
    }

    public static function apply(array $row, array $data, int $actor, ?array $person): void
    {
        $p = (int) $row['personnel_id'];
        $module = $row['module'];
        switch ($module) {
            case 'profile':
                self::profile($row, $data, $person);
                break;
            case 'service':
                if ($data['effective_on'] > today()) {
                    db()->run('INSERT INTO scheduled_service(request_id,personnel_id,effective_on) VALUES (?,?,?)',
                        [(int) $row['id'],$p,$data['effective_on']]);
                } else {
                    self::service($p,$data);
                }
                break;
            case 'dikum':
            case 'dikmil':
                $keys = ['type_id','institution','program','cohort','start_on','end_on','start_year','end_year','status','outcome'];
                $record = self::upsert('education_histories', (int) $row['education_id'], $keys, $data, ['personnel_id'=>$p,'kind'=>$module]);
                db()->run('UPDATE education_histories SET certificate_cipher=? WHERE id=?',
                    [$data['certificate'] ? crypto()->seal($data['certificate'],'certificate:' . $record) : null,$record]);
                db()->run('UPDATE change_requests SET education_id=? WHERE id=?', [$record,(int) $row['id']]);
                break;
            case 'award':
                $record = self::upsert('personnel_awards', (int) $row['award_id'],
                    ['type_id','issuer','level_name','awarded_on','decision_number','description'],$data,['personnel_id'=>$p]);
                db()->run('UPDATE change_requests SET award_id=? WHERE id=?', [$record,(int) $row['id']]);
                break;
            case 'retirement':
                db()->run("INSERT INTO retirement_forecasts(personnel_id,estimate_date,source,version,basis_version,verified_by)
                    VALUES (?,?,?,1,?,?) ON DUPLICATE KEY UPDATE estimate_date=VALUES(estimate_date), source=VALUES(source),
                    validation_status='valid', version=version+1,basis_version=VALUES(basis_version),verified_by=VALUES(verified_by)",
                    [$p,$data['estimate_date'],$data['source'],(int) $person['version'],$actor]);
                break;
            case 'case':
                $case = (int) $row['case_id'];
                $version = (int) $row['base_version'] + 1;
                db()->run('UPDATE disciplinary_cases SET content_cipher=?,version=? WHERE id=?',
                    [crypto()->seal($data,'case:' . $case),$version,$case]);
                db()->run('DELETE FROM case_personnel WHERE case_id=?', [$case]);
                foreach (self::parties($data) as $id => $role) {
                    db()->run('INSERT INTO case_personnel VALUES (?,?,?)', [$case,$id,crypto()->seal($role,'party:' . $case . ':' . $id)]);
                }
                db()->run('INSERT INTO case_events(case_id,actor_id,version,content_cipher) VALUES (?,?,?,?)',
                    [$case,$actor,$version,crypto()->seal($data,'event:' . $case . ':' . $version)]);
                break;
        }
    }

    private static function upsert(string $table, int $id, array $keys, array $data, array $parent): int
    {
        $values = array_map(static fn(string $key)=>$data[$key], $keys);
        if ($id) {
            db()->run("UPDATE $table SET " . implode('=?,', $keys) . '=?,version=version+1 WHERE id=?', [...$values,$id]);
            return $id;
        }
        $keys = [...$keys,...array_keys($parent)];
        db()->run("INSERT INTO $table(" . implode(',', $keys) . ') VALUES (' . implode(',',array_fill(0,count($keys),'?')) . ')',
            [...$values,...array_values($parent)]);
        return db()->lastId();
    }

    private static function profile(array $row, array $data, ?array $person): void
    {
        $keys = ['name','category_id','rank_id','grade_id','corps_id','profession_id','specialty'];
        $unit = (int) $row['unit_id'];
        $p = self::upsert('personnel', (int) $row['personnel_id'],$keys,$data,['unit_id'=>$unit,'service_status'=>'active']);
        $type = (int) db()->one('SELECT identifier_type_id FROM personnel_categories WHERE id=?', [$data['category_id']])['identifier_type_id'];
        $digest = crypto()->identifier($type,$data['identifier']);
        if (db()->one('SELECT 1 FROM personnel_identifiers WHERE type_id=? AND digest=? AND personnel_id<>?', [$type,$digest,$p])) {
            fail(409, 'Nomor kedinasan tidak dapat digunakan.');
        }
        db()->run('INSERT INTO personnel_identifiers VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE
            type_id=IF(personnel_id=VALUES(personnel_id),VALUES(type_id),type_id),
            digest=IF(personnel_id=VALUES(personnel_id),VALUES(digest),digest),
            cipher=IF(personnel_id=VALUES(personnel_id),VALUES(cipher),cipher)',
            [$p,$type,$digest,crypto()->seal($data['identifier'],'identifier:' . $p)]);
        if (!db()->one('SELECT 1 FROM personnel_identifiers WHERE personnel_id=? AND type_id=? AND digest=?', [$p,$type,$digest])) {
            fail(409, 'Nomor kedinasan tidak dapat digunakan.');
        }
        db()->run('UPDATE personnel SET birth_cipher=? WHERE id=?',
            [$data['birth_date'] ? crypto()->seal($data['birth_date'],'birth:' . $p) : null,$p]);
        if (!$person) {
            db()->run("INSERT INTO service_assignments(personnel_id,unit_id,service_status,start_on) VALUES (?,?,'active',?)", [$p,$unit,today()]);
            db()->run('UPDATE change_requests SET personnel_id=? WHERE id=?', [$p,(int) $row['id']]);
        } else {
            db()->run("UPDATE retirement_forecasts SET validation_status='review' WHERE personnel_id=?", [$p]);
        }
    }

    public static function service(int $p, array $data): void
    {
        $current = db()->one('SELECT id,start_on FROM service_assignments WHERE open_personnel=? FOR UPDATE', [$p]);
        if (!$current || $data['effective_on'] < $current['start_on']) {
            fail(409, 'Tanggal efektif mendahului penempatan terakhir.');
        }
        $values = [$data['unit_id'],$data['facility_id'],$data['position_title'],$data['service_status']];
        db()->run('UPDATE service_assignments SET end_before=? WHERE id=?', [$data['effective_on'],(int) $current['id']]);
        db()->run('INSERT INTO service_assignments(unit_id,facility_id,position_title,service_status,personnel_id,start_on)
            VALUES (?,?,?,?,?,?)', [...$values,$p,$data['effective_on']]);
        db()->run('UPDATE personnel SET unit_id=?,facility_id=?,position_title=?,service_status=?,version=version+1 WHERE id=?', [...$values,$p]);
        db()->run("UPDATE retirement_forecasts SET validation_status='review' WHERE personnel_id=?", [$p]);
    }
}

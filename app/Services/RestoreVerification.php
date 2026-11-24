<?php
declare(strict_types=1);
namespace Binprof\Services;

use Binprof\Repositories\Database;
use Pdo\Mysql;
use function Binprof\{config,crypto,fail};

final class RestoreVerification
{
    private const TABLE_KEYS = [
        'schema_migrations'=>'version',
        'app_settings'=>'name',
        'roles'=>'code',
        'users'=>'id',
        'user_roles'=>'user_id,role_code',
        'user_verification_modules'=>'user_id,module',
        'units'=>'id',
        'user_unit_scopes'=>'user_id,unit_id',
        'identifier_types'=>'id',
        'personnel_categories'=>'id',
        'reference_values'=>'id',
        'facilities'=>'id',
        'personnel'=>'id',
        'personnel_identifiers'=>'personnel_id',
        'service_assignments'=>'id',
        'education_histories'=>'id',
        'personnel_awards'=>'id',
        'retirement_forecasts'=>'personnel_id',
        'disciplinary_cases'=>'id',
        'case_personnel'=>'case_id,personnel_id',
        'case_events'=>'id',
        'case_access_grants'=>'case_id,user_id,capability',
        'change_requests'=>'id',
        'change_request_revisions'=>'request_id,revision',
        'approval_actions'=>'request_id,revision',
        'documents'=>'id',
        'scheduled_service'=>'request_id',
        'notifications'=>'id',
        'audit_logs'=>'id',
        'login_limits'=>'bucket',
        'job_locks'=>'name',
        'job_runs'=>'id',
    ];

    public static function capture(): array
    {
        try {
            crypto();
        } catch (\Throwable) {
            fail(422,'Keyring tidak dapat dibaca.');
        }

        $db = new Database(config());
        $db->pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY,false);
        $db->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');

        return $db->transaction(static function () use ($db): array {
            $schema = (int) $db->one('SELECT MAX(version) AS version FROM schema_migrations')['version'];
            if ($schema !== 2) {
                fail(422,'Gunakan versi verifier yang sesuai dengan skema cadangan.');
            }
            self::checkRelations($db);
            $tables = [];
            foreach (self::TABLE_KEYS as $table=>$order) {
                $tables[$table] = self::scan($db,$table,$order);
            }
            return ['format'=>1,'schema_version'=>$schema,'tables'=>$tables];
        });
    }

    private static function checkRelations(Database $db): void
    {
        $checks = [
            'personnel_identifiers'=>'SELECT 1 FROM personnel p LEFT JOIN personnel_identifiers i ON i.personnel_id=p.id
                WHERE i.personnel_id IS NULL',
            'case_personnel'=>'SELECT 1 FROM case_personnel cp LEFT JOIN disciplinary_cases c ON c.id=cp.case_id
                LEFT JOIN personnel p ON p.id=cp.personnel_id WHERE c.id IS NULL OR p.id IS NULL',
            'change_request_revisions'=>'SELECT 1 FROM change_requests r LEFT JOIN change_request_revisions v
                ON v.request_id=r.id AND v.revision=r.revision WHERE v.request_id IS NULL',
            'documents'=>'SELECT 1 FROM documents d LEFT JOIN change_request_revisions v
                ON v.request_id=d.request_id AND v.revision=d.revision WHERE v.request_id IS NULL',
        ];
        foreach ($checks as $name=>$sql) {
            if ($db->one($sql . ' LIMIT 1')) {
                fail(409,'Relasi cadangan tidak lengkap pada ' . $name . '.');
            }
        }
    }

    private static function scan(Database $db, string $table, string $order): array
    {
        $hash = hash_init('sha256');
        $rows = 0;
        // Table names and primary key order come only from TABLE_KEYS.
        $result = $db->run("SELECT * FROM $table ORDER BY $order");
        try {
            while ($row = $result->fetch()) {
                try {
                    self::checkEncryptedData($table,$row);
                } catch (\Throwable) {
                    fail(409,'Integritas data terenkripsi atau berkas gagal pada ' . $table . '.');
                }
                $values = array_map(static fn(mixed $value): ?string => $value === null ? null : (string) $value,$row);
                hash_update($hash,serialize($values));
                $rows++;
            }
        } finally {
            $result->closeCursor();
        }
        return ['rows'=>$rows,'sha256'=>hash_final($hash)];
    }

    private static function checkEncryptedData(string $table, array $row): void
    {
        if ($table === 'documents') {
            Documents::checkIntegrity($row);
            return;
        }
        if ($table === 'personnel_identifiers') {
            $number = crypto()->open($row['cipher'],'identifier:' . $row['personnel_id']);
            if (!is_string($number) || !hash_equals($row['digest'],crypto()->identifier((int) $row['type_id'],$number))) {
                throw new \RuntimeException('Identifier digest mismatch');
            }
            return;
        }
        $contexts = match ($table) {
            'personnel'=>['birth_cipher'=>'birth:' . $row['id']],
            'education_histories'=>['certificate_cipher'=>'certificate:' . $row['id']],
            'disciplinary_cases'=>['content_cipher'=>'case:' . $row['id']],
            'case_personnel'=>['role_cipher'=>'party:' . $row['case_id'] . ':' . $row['personnel_id']],
            'case_events'=>['content_cipher'=>'event:' . $row['case_id'] . ':' . $row['version']],
            'change_request_revisions'=>[
                'payload_cipher'=>'revision:' . $row['request_id'] . ':' . $row['revision'],
                'before_cipher'=>'revision:' . $row['request_id'] . ':' . $row['revision'] . ':before',
            ],
            'approval_actions'=>['comment_cipher'=>'revision:' . $row['request_id'] . ':' . $row['revision'] . ':decision'],
            default=>[],
        };
        foreach ($contexts as $field=>$context) {
            if ($row[$field] !== null) {
                crypto()->open($row[$field],$context);
            }
        }
    }

    public static function compare(array $expected, array $actual): void
    {
        if (($expected['format'] ?? null) !== $actual['format']
            || ($expected['schema_version'] ?? null) !== $actual['schema_version']
            || !is_array($expected['tables'] ?? null) || count($expected['tables']) !== count(self::TABLE_KEYS)) {
            fail(422,'Format, skema, atau cakupan tabel manifest tidak sesuai.');
        }
        $different = [];
        foreach ($actual['tables'] as $table=>$value) {
            $baseline = $expected['tables'][$table] ?? null;
            if (!is_array($baseline) || !is_int($baseline['rows'] ?? null) || $baseline['rows'] < 0
                || !is_string($baseline['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$baseline['sha256'])) {
                fail(422,'Manifest cadangan tidak valid.');
            }
            if ($baseline['rows'] !== $value['rows'] || !hash_equals($baseline['sha256'],$value['sha256'])) {
                $different[] = $table;
            }
        }
        if ($different) {
            fail(409,'Cadangan berbeda pada tabel ' . implode(', ',$different) . '.');
        }
    }
}

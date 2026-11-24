<?php
declare(strict_types=1);
namespace Binprof\Services;

use Binprof\Policies\Access;
use function Binprof\{audit,crypto,db,fail,id,today};

final class Changes
{
    public const STATUS = ['draft'=>'Draf','submitted'=>'Diajukan','returned'=>'Dikembalikan','approved'=>'Disetujui','cancelled'=>'Dibatalkan'];

    public static function row(int $id, bool $lock = false): array
    {
        $request = db()->one('SELECT * FROM change_requests WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id])
            ?? fail(404, 'Pengajuan tidak ditemukan.');
        $request['current_unit'] = $request['personnel_id']
            ? db()->one('SELECT unit_id FROM personnel WHERE id=?', [(int) $request['personnel_id']])['unit_id'] : null;
        return $request;
    }

    public static function revision(array $row): array
    {
        return db()->one('SELECT * FROM change_request_revisions WHERE request_id=? AND revision=?',
            [(int) $row['id'], (int) $row['revision']]);
    }

    public static function context(array $row): string
    {
        return 'revision:' . $row['id'] . ':' . $row['revision'];
    }

    public static function canReview(Access $user, array $row, string $action): void
    {
        if (!$user->request($row, $action)) {
            fail(404, 'Pengajuan tidak ditemukan.');
        }
    }

    public static function create(Access $user, string $module, int $personId, int $unit, int $item, array $input): int
    {
        $data = Records::normalize($module,$input);
        return db()->transaction(static function () use ($user,$module,$personId,$unit,$item,$data): int {
            $user = Access::load($user->id,true) ?? fail(403, 'Akun tidak aktif.');
            $case = null;
            $education = in_array($module,['dikum','dikmil'],true) && $item ? $item : null;
            $award = $module === 'award' && $item ? $item : null;
            if ($module === 'case') {
                $case = $item ?: self::newCase($user,$unit);
                $user->caseRequire($case,'read','write');
                $unit = (int) db()->one('SELECT unit_id FROM disciplinary_cases WHERE id=?', [$case])['unit_id'];
                $personId = 0;
            } else {
                $user->require('write');
                if ($personId) {
                    $unit = (int) $user->person($personId,true)['unit_id'];
                } elseif ($module !== 'profile' || !$user->unit($unit) || !db()->one('SELECT 1 FROM units WHERE id=? AND active=1', [$unit])) {
                    fail(404, 'Satuan atau personel tidak ditemukan.');
                }
            }
            $row = ['module'=>$module,'personnel_id'=>$personId ?: null,'case_id'=>$case,'education_id'=>$education,'award_id'=>$award];
            [$version,$before] = Records::current($row);
            db()->run('INSERT INTO change_requests(module,unit_id,personnel_id,case_id,education_id,award_id,owner_id)
                VALUES (?,?,?,?,?,?,?)', [$module,$unit,$personId ?: null,$case,$education,$award,$user->id]);
            $id = db()->lastId();
            self::snapshot($id,1,$version,$before,$data);
            audit($user->id,'draft.create',$id);
            return $id;
        });
    }

    private static function newCase(Access $user, int $unit): int
    {
        if (!$user->account['case_creator'] || !$user->unit($unit)
            || !db()->one('SELECT 1 FROM units WHERE id=? AND active=1', [$unit])) {
            fail(404, 'Satuan penanggung jawab tidak ditemukan.');
        }
        db()->run('INSERT INTO disciplinary_cases(unit_id) VALUES (?)', [$unit]);
        $id = db()->lastId();
        foreach (['read','write','submit'] as $capability) {
            db()->run('INSERT INTO case_access_grants(case_id,user_id,capability,granted_by) VALUES (?,?,?,?)',
                [$id,$user->id,$capability,$user->id]);
        }
        audit($user->id,'case.create',$id);
        return $id;
    }

    private static function snapshot(int $id, int $rev, int $version, array $before, array $data): void
    {
        $context = self::context(['id'=>$id,'revision'=>$rev]);
        db()->run('INSERT INTO change_request_revisions(request_id,revision,base_version,before_cipher,payload_cipher)
            VALUES (?,?,?,?,?)', [$id,$rev,$version,crypto()->seal($before,$context . ':before'),crypto()->seal($data,$context)]);
    }

    public static function mutate(Access $user, int $id, int $expected, string $action, array $input): void
    {
        db()->transaction(static function () use ($user,$id,$expected,$action,$input): void {
            $row = self::row($id,true);
            $ownerId = (int) $row['owner_id'];
            $actors = array_unique([$user->id,$ownerId]);
            sort($actors,SORT_NUMERIC);
            $users = [];
            foreach ($actors as $actorId) {
                $users[$actorId] = Access::load($actorId,true);
            }
            $lockedUser = $users[$user->id] ?? fail(403, 'Akun tidak aktif.');
            if ($lockedUser->account['session_version'] !== $user->account['session_version']) {
                fail(403, 'Silakan masuk kembali.');
            }
            if ($row['personnel_id']) {
                $row['current_unit'] = db()->one('SELECT unit_id FROM personnel WHERE id=? FOR UPDATE', [(int) $row['personnel_id']])['unit_id'];
            }
            if (in_array($action,['approve','return'],true) && $lockedUser->id === $ownerId) {
                self::canReview($lockedUser,$row,'read');
                fail(403, 'Pemohon tidak dapat memeriksa pengajuan sendiri.');
            }
            $capability = match ($action) {
                'approve','return'=>'verify','submit'=>'submit','edit','revise','rebase','cancel'=>'write',
                default=>fail(404, 'Tindakan tidak ditemukan.'),
            };
            self::canReview($lockedUser,$row,$capability);
            $state = $row['status'];
            $revisionNumber = (int) $row['revision'];
            $allowed = match ($action) {
                'edit','submit','rebase'=>$state === 'draft',
                'revise'=>$state === 'returned',
                'approve','return'=>$state === 'submitted',
                'cancel'=>in_array($state,['draft','submitted','returned'],true),
            };
            if (!$allowed || $expected !== $revisionNumber) {
                fail(409, 'Status atau revisi sudah berubah. Muat ulang pengajuan.');
            }
            $revision = self::revision($row);
            $context = self::context($row);
            $data = crypto()->open($revision['payload_cipher'],$context);
            if ($action === 'edit') {
                db()->run('UPDATE change_request_revisions SET payload_cipher=? WHERE request_id=? AND revision=? AND submitted_at IS NULL',
                    [crypto()->seal(Records::normalize($row['module'],$input),$context),$id,$revisionNumber]);
            }
            if ($action === 'revise' || $action === 'rebase') {
                [$version,$before] = Records::current($row,true);
                self::snapshot($id,$revisionNumber + 1,$version,$before,$data);
                db()->run("UPDATE change_requests SET revision=revision+1,status='draft' WHERE id=?", [$id]);
            }
            if (in_array($action,['submit','approve'],true)) {
                $owner = $users[$ownerId];
                if (!$owner || !$owner->request($row,'submit')) {
                    fail(409, 'Pemohon sudah tidak berwenang untuk pengajuan ini.');
                }
                [$version,$before,$person] = Records::current($row,true);
                $base = crypto()->open($revision['before_cipher'],$context . ':before');
                if ($version !== (int) $revision['base_version'] || ($before['_basis'] ?? null) !== ($base['_basis'] ?? null)) {
                    fail(409, 'Data dasar sudah berubah. Gunakan revisi berdasarkan versi terbaru.');
                }
                Records::validate($row['module'],$data,$person,$owner);
                if ($action === 'submit') {
                    db()->run('UPDATE change_request_revisions SET submitted_at=UTC_TIMESTAMP() WHERE request_id=? AND revision=?',
                        [$id,$revisionNumber]);
                    db()->run("UPDATE change_requests SET status='submitted' WHERE id=?", [$id]);
                } else {
                    if ($row['module'] === 'service' && !$lockedUser->unit($data['unit_id'])) {
                        fail(404, 'Satuan tujuan di luar cakupan pemeriksa.');
                    }
                    Documents::checkRevision($id,$revisionNumber);
                    Records::apply($row + $revision,$data,$lockedUser->id,$person);
                    self::decide($row,$lockedUser,'approved',$input);
                }
            }
            if ($action === 'return') {
                self::decide($row,$lockedUser,'returned',$input);
            }
            if ($action === 'cancel') {
                if ($state === 'returned') {
                    db()->run("UPDATE change_requests SET status='cancelled' WHERE id=?", [$id]);
                } else {
                    self::decide($row,$lockedUser,'cancelled',$input);
                }
            }
            audit($lockedUser->id,'request.' . $action,$id);
        });
    }

    private static function decide(array $row, Access $user, string $status, array $input): void
    {
        $comment = Records::text($input['comment'] ?? null,500);
        if ($status === 'returned' && !$comment) {
            fail(422, 'Isi catatan pengembalian.');
        }
        $id = (int) $row['id'];
        db()->run('INSERT INTO approval_actions(request_id,revision,actor_id,decision,comment_cipher) VALUES (?,?,?,?,?)',
            [$id,(int) $row['revision'],$user->id,$status,crypto()->seal($comment,self::context($row) . ':decision')]);
        db()->run('UPDATE change_requests SET status=? WHERE id=?', [$status,$id]);
        if ($status !== 'cancelled') {
            $recipient = (int) $row['owner_id'];
            db()->run('INSERT INTO notifications(dedupe_key,recipient_id,request_id,kind) VALUES (?,?,?,?)',
                [hash('sha256',self::context($row) . ':' . $status),$recipient,$id,'verification']);
        }
    }

    public static function queue(Access $user, int $after, bool $pendingOnly = false): array
    {
        return db()->all("SELECT r.id,r.module,r.status,r.created_at FROM change_requests r
            LEFT JOIN personnel p ON p.id=r.personnel_id
            LEFT JOIN user_unit_scopes s ON s.unit_id=r.unit_id AND s.user_id=?
            LEFT JOIN user_unit_scopes c ON c.unit_id=p.unit_id AND c.user_id=?
            LEFT JOIN case_access_grants gr ON gr.case_id=r.case_id AND gr.user_id=? AND gr.capability='read' AND (gr.expires_on IS NULL OR gr.expires_on>=?)
            LEFT JOIN case_access_grants gw ON gw.case_id=r.case_id AND gw.user_id=? AND gw.capability='write' AND (gw.expires_on IS NULL OR gw.expires_on>=?)
            LEFT JOIN case_access_grants gv ON gv.case_id=r.case_id AND gv.user_id=? AND gv.capability='verify' AND (gv.expires_on IS NULL OR gv.expires_on>=?)
            WHERE r.id<? AND (?=0 OR r.status='submitted') AND (
              (r.module<>'case' AND s.user_id IS NOT NULL AND (r.personnel_id IS NULL OR c.user_id IS NOT NULL)
                   AND ((?=1 AND r.owner_id=?) OR (?=1 AND r.status IN ('submitted','returned','approved')
                   AND EXISTS (SELECT 1 FROM user_verification_modules vm WHERE vm.user_id=? AND vm.module=r.module))))
              OR (r.module='case' AND gr.user_id IS NOT NULL AND
                   ((gw.user_id IS NOT NULL AND r.owner_id=?) OR (gv.user_id IS NOT NULL AND r.status IN ('submitted','returned','approved'))))
            ) ORDER BY r.id DESC LIMIT 51",
            [$user->id,$user->id,$user->id,today(),$user->id,today(),$user->id,today(),$after,(int) $pendingOnly,
                (int) $user->allows('write'),$user->id,(int) $user->allows('verify'),$user->id,$user->id]);
    }
}

<?php
declare(strict_types=1);
namespace Binprof\Services;

use Binprof\Policies\Access;
use function Binprof\{db,fail,today};

final class Inbox
{
    public static function rows(Access $user, int $before, int $limit = 51, ?int $notification = null): array
    {
        return db()->all("SELECT n.id,n.personnel_id,n.request_id,n.kind,n.read_at,n.created_at
            FROM notifications n
            LEFT JOIN change_requests r ON r.id=n.request_id
            LEFT JOIN personnel p ON p.id=COALESCE(n.personnel_id,r.personnel_id)
            LEFT JOIN retirement_forecasts f ON f.personnel_id=n.personnel_id
            LEFT JOIN user_unit_scopes s ON s.unit_id=p.unit_id AND s.user_id=?
            LEFT JOIN user_unit_scopes sr ON sr.unit_id=r.unit_id AND sr.user_id=?
            WHERE n.recipient_id=? AND n.id<? AND (? IS NULL OR n.id=?) AND (
              (n.personnel_id IS NOT NULL AND ?=1 AND s.user_id IS NOT NULL AND p.service_status='active'
                AND f.validation_status='valid' AND f.version=n.forecast_version
                AND n.rule_version=(SELECT CAST(value AS UNSIGNED) FROM app_settings WHERE name='reminder_version')
                AND (f.estimate_date>=? OR n.kind='confirm'))
              OR (r.owner_id=? AND (
                (r.module<>'case' AND ?=1 AND sr.user_id IS NOT NULL AND (p.id IS NULL OR s.user_id IS NOT NULL))
                OR (r.module='case' AND 2=(SELECT COUNT(*) FROM case_access_grants g WHERE g.case_id=r.case_id
                     AND g.user_id=? AND g.capability IN ('read','write') AND (g.expires_on IS NULL OR g.expires_on>=?)))
              ))
            ) ORDER BY n.id DESC LIMIT ?",
            [$user->id,$user->id,$user->id,$before,$notification,$notification,(int) $user->allows('read'),today(),
                $user->id,(int) $user->allows('write'),$user->id,today(),$limit]);
    }

    public static function markRead(Access $user, int $id): void
    {
        if (!self::rows($user,PHP_INT_MAX,1,$id)) {
            fail(404,'Notifikasi tidak ditemukan.');
        }
        db()->run('UPDATE notifications SET read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE id=? AND recipient_id=?', [$id,$user->id]);
    }

    public static function label(string $kind): string
    {
        return match ($kind) {
            'verification'=>'Ada pembaruan pengajuan.',
            'confirm'=>'Tanggal perkiraan telah lewat. Perlu konfirmasi.',
            default=>'Pengingat perkiraan pensiun · ambang ' . substr($kind,7) . ' bulan',
        };
    }
}

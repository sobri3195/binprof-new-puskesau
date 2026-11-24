<?php
declare(strict_types=1);
namespace Binprof\Services;

use Binprof\Policies\Access;
use DateTimeImmutable;
use function Binprof\{audit,db,fail,id,today};

final class Staffing
{
    public static function period(mixed $month): string
    {
        if (!is_string($month) || !Records::date($month . '-01')) {
            fail(422,'Pilih bulan dan tahun kebutuhan yang valid.');
        }
        return $month . '-01';
    }

    public static function asOf(string $period): ?string
    {
        $today = today();
        return $period > $today ? null : min($today,(new DateTimeImmutable($period))->modify('last day of this month')->format('Y-m-d'));
    }

    public static function rows(Access $user, string $period, ?string $date, int $offset): array
    {
        if ($user->allows('read')) {
            return db()->all("SELECT u.name AS unit,t.position_title,t.required_count,
                    CASE WHEN ? IS NULL THEN NULL ELSE COUNT(DISTINCT a.personnel_id) END AS filled
                FROM staffing_targets t JOIN units u ON u.id=t.unit_id
                JOIN user_unit_scopes s ON s.unit_id=t.unit_id AND s.user_id=?
                LEFT JOIN service_assignments a ON a.unit_id=t.unit_id AND a.position_title=t.position_title
                    AND a.service_status='active' AND a.start_on<=? AND (a.end_before IS NULL OR a.end_before>?)
                WHERE t.period=? GROUP BY t.id,u.name,t.position_title,t.required_count
                ORDER BY u.name,t.position_title,t.id LIMIT 51 OFFSET ?",
                [$date,$user->id,$date,$date,$period,$offset]);
        }
        $user->require('admin');
        return db()->all('SELECT u.name AS unit,t.position_title,t.required_count,NULL AS filled
            FROM staffing_targets t JOIN units u ON u.id=t.unit_id WHERE t.period=?
            ORDER BY u.name,t.position_title,t.id LIMIT 51 OFFSET ?',[$period,$offset]);
    }

    public static function save(Access $actor, array $input): string
    {
        $actor->require('admin');
        $period = self::period($input['period'] ?? null);
        $unit = id($input['unit_id'] ?? '');
        $title = Records::text($input['position_title'] ?? null,160);
        $count = $input['required_count'] ?? null;
        if (!$unit || !$title || !is_string($count) || !preg_match('/^\d{1,6}$/D',$count)) {
            fail(422,'Isi satuan, jabatan, dan jumlah 0–999.999.');
        }
        db()->transaction(static function () use ($actor,$period,$unit,$title,$count): void {
            $current = Access::load($actor->id,true) ?? fail(403,'Akun tidak aktif.');
            $current->require('admin');
            if ($current->account['session_version'] !== $actor->account['session_version']) {
                fail(403,'Silakan masuk kembali.');
            }
            if (!db()->one('SELECT 1 FROM units WHERE id=?',[$unit])) {
                fail(422,'Satuan tidak ditemukan.');
            }
            db()->run('INSERT INTO staffing_targets(unit_id,period,position_title,required_count) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE required_count=VALUES(required_count)',[$unit,$period,$title,(int) $count]);
            audit($actor->id,'staffing.target',$unit);
        });
        return substr($period,0,7);
    }
}

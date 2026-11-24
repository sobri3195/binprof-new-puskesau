<?php
declare(strict_types=1);
namespace Binprof\Services;

use DateTimeImmutable;
use function Binprof\{audit,crypto,db,fail,today};

final class Jobs
{
    public static function subtractMonths(string $date, int $months): string
    {
        $source = new DateTimeImmutable($date);
        $month = $source->modify('first day of this month')->modify('-' . $months . ' months');
        return $month->format('Y-m-') . sprintf('%02d',min((int) $source->format('j'),(int) $month->format('t')));
    }

    public static function rules(): array
    {
        return array_column(db()->all("SELECT name,value FROM app_settings WHERE name IN ('reminders','reminder_version')"),'value','name');
    }

    public static function updateRules(int $actor, mixed $raw): void
    {
        if (!is_string($raw) || !preg_match('/^[1-9][0-9]?(,[1-9][0-9]?){0,11}$/D',$raw)) {
            fail(422,'Gunakan daftar bulan berbeda (1–99), dipisahkan koma.');
        }
        $months = array_unique(array_map('intval',explode(',',$raw)));
        rsort($months,SORT_NUMERIC);
        db()->transaction(static function () use ($actor,$months): void {
            (\Binprof\Policies\Access::load($actor,true) ?? fail(403,'Akun tidak aktif.'))->require('jobs');
            $old = db()->one("SELECT value FROM app_settings WHERE name='reminders' FOR UPDATE")['value'];
            $new = implode(',',$months);
            if ($new !== $old) {
                db()->run("UPDATE app_settings SET value=? WHERE name='reminders'", [$new]);
                db()->run("UPDATE app_settings SET value=CAST(value AS UNSIGNED)+1 WHERE name='reminder_version'");
                audit($actor,'job.rules');
            }
        });
    }

    public static function run(?string $date = null): array
    {
        $date ??= today();
        $token = bin2hex(random_bytes(16));
        if (!db()->run("UPDATE job_locks SET token=?,expires_at=UTC_TIMESTAMP()+INTERVAL 2 MINUTE WHERE name='daily'
            AND (token IS NULL OR expires_at<=UTC_TIMESTAMP())", [$token])->rowCount()) {
            return ['state'=>'busy','processed'=>0,'failures'=>0];
        }
        $processed = 0;
        $failures = 0;
        $run = null;
        $state = 'ok';
        $deadline = microtime(true) + 20;
        try {
            db()->run('INSERT INTO job_runs () VALUES ()');
            $run = db()->lastId();
            do {
                $batch = db()->all("SELECT request_id FROM scheduled_service WHERE state='scheduled'
                    AND effective_on<=? ORDER BY effective_on,request_id LIMIT 100", [$date]);
                foreach ($batch as $item) {
                    $after = (int) $item['request_id'];
                    try {
                        self::lease($token,static fn()=>self::applyService($after));
                        $processed++;
                    } catch (\Binprof\Problem $exception) {
                        if ($exception->getCode() !== 422 && $exception->getCode() !== 409) {
                            throw $exception;
                        }
                        self::lease($token,static fn()=>db()->run("UPDATE scheduled_service SET state='conflict' WHERE request_id=?", [$after]));
                        $failures++;
                    }
                }
            } while (count($batch) === 100 && microtime(true) < $deadline);
            $after = (int) (db()->one("SELECT value FROM app_settings WHERE name='forecast_cursor'")['value'] ?? 0);
            do {
                $forecasts = db()->all("SELECT personnel_id FROM retirement_forecasts WHERE validation_status='valid'
                    AND personnel_id>? ORDER BY personnel_id LIMIT 100", [$after]);
                self::lease($token,static function () use ($forecasts,$date,&$after): void {
                    $rules = self::rules();
                    foreach ($forecasts as $forecast) {
                        $after = (int) $forecast['personnel_id'];
                        self::remind($after,$date,$rules);
                    }
                    db()->run("INSERT INTO app_settings(name,value) VALUES ('forecast_cursor',?)
                        ON DUPLICATE KEY UPDATE value=VALUES(value)", [count($forecasts) === 100 ? (string) $after : '0']);
                });
                $processed += count($forecasts);
            } while (count($forecasts) === 100 && microtime(true) < $deadline);
            $state = $failures ? 'conflicts' : (count($batch) === 100 || count($forecasts) === 100 ? 'partial' : 'ok');
        } catch (\Throwable $exception) {
            $state = 'error';
            $failures++;
            error_log('BINPROF job failure ' . get_class($exception));
        } finally {
            if ($run !== null) {
                db()->run('UPDATE job_runs SET finished_at=UTC_TIMESTAMP(),processed=?,failures=?,reason=? WHERE id=?',
                    [$processed,$failures,$state,$run]);
            }
            db()->run("UPDATE job_locks SET token=NULL,expires_at=NULL WHERE name='daily' AND token=?", [$token]);
        }
        return ['state'=>$state,'processed'=>$processed,'failures'=>$failures];
    }

    private static function lease(string $token, callable $process): void
    {
        db()->transaction(static function () use ($token,$process): void {
            $lock = db()->one("SELECT token,expires_at>UTC_TIMESTAMP() AS usable FROM job_locks WHERE name='daily' FOR UPDATE");
            if ($lock['token'] !== $token || !$lock['usable']) {
                throw new \RuntimeException('Job lease lost');
            }
            $process();
            db()->run("UPDATE job_locks SET expires_at=UTC_TIMESTAMP()+INTERVAL 2 MINUTE WHERE name='daily'");
        });
    }

    private static function applyService(int $id): void
    {
        $r = Changes::row($id,true);
        $revision = Changes::revision($r);
        [$version,,$person] = Records::current($r,true);
        $data = crypto()->open($revision['payload_cipher'],Changes::context($r));
        if ($version !== (int) $revision['base_version'] || !db()->one('SELECT 1 FROM units WHERE id=? AND active=1',[$data['unit_id']])
            || ($data['facility_id'] && !db()->one('SELECT 1 FROM facilities WHERE id=? AND active=1 AND unit_id=?',
                [$data['facility_id'],$data['unit_id']]))) {
            fail(409,'Perubahan terjadwal memerlukan pemeriksaan ulang.');
        }
        Records::service((int) $person['id'],$data);
        db()->run("UPDATE scheduled_service SET state='applied' WHERE request_id=?", [$id]);
        audit(null,'service.apply',$id);
    }

    private static function remind(int $id, string $date, array $rules): void
    {
        $row = db()->one("SELECT f.estimate_date,f.version FROM personnel p JOIN retirement_forecasts f ON f.personnel_id=p.id
            WHERE p.id=? AND p.service_status='active' AND f.validation_status='valid' FOR UPDATE", [$id]);
        if (!$row) {
            return;
        }
        $kind = null;
        if ($row['estimate_date'] < $date) {
            $kind = 'confirm';
        } else {
            foreach (explode(',',$rules['reminders']) as $month) {
                if (self::subtractMonths($row['estimate_date'],(int) $month) <= $date) {
                    $kind = 'months_' . $month;
                }
            }
        }
        if ($kind === null) {
            return;
        }
        $forecast = (int) $row['version'];
        $rule = (int) $rules['reminder_version'];
        $recipients = db()->all("SELECT DISTINCT u.id FROM users u JOIN user_unit_scopes s ON s.user_id=u.id
            JOIN personnel p ON p.unit_id=s.unit_id JOIN user_roles ur ON ur.user_id=u.id
            WHERE u.active=1 AND p.id=? AND ur.role_code IN ('operator','verifier','leader')", [$id]);
        foreach ($recipients as $recipient) {
            $user = (int) $recipient['id'];
            $key = hash('sha256',implode(':',[$id,$forecast,$rule,$kind,$user]));
            db()->run('INSERT INTO notifications(dedupe_key,recipient_id,personnel_id,forecast_version,rule_version,kind)
                VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE dedupe_key=dedupe_key', [$key,$user,$id,$forecast,$rule,$kind]);
        }
    }
}

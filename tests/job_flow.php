<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cli/common.php';

use Binprof\Services\Jobs;
use function Binprof\db;

cli(static function (): int {
    $date = $_SERVER['argv'][1] ?? '';
    if (Jobs::subtractMonths('2027-03-31',1) !== '2027-02-28' || Jobs::subtractMonths('2028-03-31',1) !== '2028-02-29') {
        throw new RuntimeException('calendar clamp');
    }
    db()->run("UPDATE job_locks SET token=REPEAT('a',32),expires_at=UTC_TIMESTAMP()+INTERVAL 1 MINUTE WHERE name='daily'");
    if (Jobs::run($date)['state'] !== 'busy') {
        throw new RuntimeException('active lock');
    }
    db()->run("UPDATE job_locks SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE name='daily'");
    if (Jobs::run($date)['state'] !== 'ok') {
        throw new RuntimeException('expired lease');
    }
    if (db()->one('SELECT COUNT(*) AS n FROM service_assignments WHERE personnel_id=1 AND end_before IS NULL')['n'] !== 1
        || db()->one('SELECT unit_id FROM personnel WHERE id=1')['unit_id'] !== 2
        || db()->one('SELECT validation_status FROM retirement_forecasts WHERE personnel_id=1')['validation_status'] !== 'review') {
        throw new RuntimeException('scheduled service state');
    }
    echo "PASS akhir bulan/kabisat, lock aktif/kedaluwarsa, mutasi efektif dan invalidasi perkiraan.\n";
    return 0;
});

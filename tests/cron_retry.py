"""Inject a write failure only into the disposable restored test database."""
import json
import os
import subprocess

php = os.environ.get("BINPROF_TEST_PHP", "php")


def sql(code):
    return subprocess.check_output([php, "-r", 'require "app/bootstrap.php"; ' + code], text=True)


def run():
    process = subprocess.run([php, "cli/cron.php"], capture_output=True, text=True, timeout=10)
    return process.returncode, json.loads(process.stdout)


setup = '''
$db=Binprof\\db();
if ($db->one("SELECT DATABASE() AS db")["db"] !== "binprof_restore") {
    throw new RuntimeException("This test requires the binprof_restore database");
}
$db->run("DELETE FROM notifications WHERE forecast_version=9001");
$db->run("UPDATE retirement_forecasts SET validation_status='valid',
    estimate_date=?,version=9001 WHERE personnel_id=1",[
    (new DateTimeImmutable(Binprof\\today()))->modify("+75 days")->format("Y-m-d")
]);
$db->pdo->exec("CREATE TRIGGER test_block_reminder BEFORE INSERT ON notifications
    FOR EACH ROW BEGIN IF NEW.forecast_version=9001 AND NEW.recipient_id=4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic cron interruption';
    END IF; END");
'''
sql(setup)
count = 'echo Binprof\\db()->one("SELECT COUNT(*) AS n FROM notifications WHERE forecast_version=9001")["n"];'
try:
    exit_code, result = run()
    assert exit_code == 1 and result["state"] == "error" and sql(count) == "0"
finally:
    sql('Binprof\\db()->pdo->exec("DROP TRIGGER test_block_reminder");')

workers = [subprocess.Popen([php,"cli/cron.php"], stdout=subprocess.PIPE,text=True) for _ in range(2)]
for worker in workers:
    output, _ = worker.communicate(timeout=10)
    assert worker.returncode == 0 and json.loads(output)["state"] in {"busy", "ok"}
assert sql(count) == "3"
assert run()[0] == 0 and sql(count) == "3"
print("PASS rollback pengingat sebelum commit, retry paralel, dan deduplikasi setelah commit.")

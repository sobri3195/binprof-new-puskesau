"""Run after http_flow.py with its synthetic fixtures."""
import os
import subprocess

php = os.environ.get("BINPROF_TEST_PHP", "php")
tool = [php, "tests/concurrency.php"]
request = subprocess.check_output([*tool, "prepare"], text=True)
workers = [subprocess.Popen([*tool, "approve", request], stdout=subprocess.PIPE, text=True)
           for _ in range(2)]
states = []
for worker in workers:
    output, _ = worker.communicate(timeout=10)
    assert worker.returncode == 0
    states.append(output)
assert sorted(states) == ["approved", "conflict"], states
subprocess.run([*tool, "check", request], check=True)
print("PASS dua proses persetujuan: satu keputusan dan tepat satu penghargaan terbit.")

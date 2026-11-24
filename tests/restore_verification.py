"""Verify a quiescent synthetic source and its separate restored database."""
import base64
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

PHP = os.environ.get("BINPROF_TEST_PHP", "php")
ROOT = Path(__file__).resolve().parents[1]
VERIFY = ROOT / "cli" / "verify-restore.php"


def php(config, *args, expected=0):
    result = subprocess.run([PHP, *map(str, args)], cwd=ROOT, capture_output=True,
                            text=True, timeout=30, env=dict(os.environ, BINPROF_CONFIG=str(config)))
    assert result.returncode == expected, (result.returncode, result.stderr)
    return result


def fixture_metadata(config):
    result = php(config, "-r", """
require 'app/bootstrap.php';
$db = Binprof\\db();
echo json_encode([
    'database' => $db->one('SELECT DATABASE() AS name')['name'],
    'storage' => Binprof\\config()['storage'],
    'keys' => Binprof\\config()['keys'],
    'file' => $db->one('SELECT storage_key FROM documents ORDER BY id LIMIT 1')['storage_key'],
], JSON_THROW_ON_ERROR);
""")
    return json.loads(result.stdout)


def check_failure(config, baseline, message):
    failed = php(config, VERIFY, "--expect", baseline, expected=1)
    assert not failed.stdout and message in failed.stderr, failed.stderr


def main():
    source_config = Path(os.environ["BINPROF_CONFIG"]).resolve(strict=True)
    restored_config = Path(os.environ["BINPROF_RESTORE_CONFIG"]).resolve(strict=True)
    source = fixture_metadata(source_config)
    restored = fixture_metadata(restored_config)
    assert source["database"] != restored["database"]
    assert Path(source["storage"]).resolve() != Path(restored["storage"]).resolve()

    with tempfile.TemporaryDirectory(prefix="binprof-restore-fixture-") as directory:
        work = Path(directory)
        original = php(source_config, VERIFY)
        manifest = json.loads(original.stdout)
        assert manifest["tables"]["documents"]["rows"] > 0
        assert manifest["tables"]["personnel_identifiers"]["rows"] > 0
        assert manifest["tables"]["case_personnel"]["rows"] > 0
        baseline = work / "baseline.json"
        baseline.write_text(original.stdout)
        recovered = php(restored_config, VERIFY, "--expect", baseline)
        assert json.loads(recovered.stdout) == manifest
        print(f"PASS dump dan restore terpisah cocok pada {len(manifest['tables'])} tabel "
              f"dan {manifest['tables']['documents']['rows']} lampiran.")

        # Point all failure cases at temporary files. The restored database stays read-only.
        documents = work / "documents"
        shutil.copytree(Path(restored["storage"]) / "documents", documents)
        key_path = work / "keys.json"
        keyring = json.loads(Path(restored["keys"]).read_text())
        key_path.write_text(json.dumps(keyring))
        override = work / "fixture.php"
        override.write_text("""<?php
$source = require getenv('BINPROF_RESTORE_CONFIG');
$source['storage'] = __DIR__;
$source['keys'] = __DIR__ . '/keys.json';
return $source;
""")
        file = documents / restored["file"]
        envelope = file.read_bytes()
        file.unlink()
        check_failure(override, baseline, "gagal pada documents")
        version, encoded = envelope.split(b":", 1)
        cipher = bytearray(base64.b64decode(encoded))
        cipher[-1] ^= 1
        file.write_bytes(version + b":" + base64.b64encode(cipher))
        check_failure(override, baseline, "gagal pada documents")
        file.write_bytes(envelope)
        print("PASS lampiran hilang dan perubahan ciphertext terdeteksi.")

        rotated = dict(keyring,active="fixture-next",
                       keys={**keyring["keys"], "fixture-next":base64.b64encode(os.urandom(32)).decode()})
        key_path.write_text(json.dumps(rotated))
        php(override, VERIFY, "--expect", baseline)
        del rotated["keys"][version.decode()]
        key_path.write_text(json.dumps(rotated))
        failed = php(override, VERIFY, "--expect", baseline, expected=1)
        assert not failed.stdout and "Integritas data terenkripsi" in failed.stderr
        print("PASS data lama dapat dibaca sesudah rotasi selama versi kuncinya tersedia.")

        wrong_digest = base64.b64encode(os.urandom(32)).decode()
        key_path.write_text(json.dumps(dict(keyring, digest=wrong_digest)))
        check_failure(override, baseline, "gagal pada personnel_identifiers")
        wrong_keys = {version: base64.b64encode(os.urandom(32)).decode() for version in keyring["keys"]}
        key_path.write_text(json.dumps(dict(keyring, keys=wrong_keys)))
        failed = php(override, VERIFY, "--expect", baseline, expected=1)
        assert not failed.stdout and "Integritas data terenkripsi" in failed.stderr
        key_path.write_text("{}")
        check_failure(override, baseline, "Keyring tidak dapat dibaca")
        print("PASS keyring salah, kunci digest berbeda, dan keyring rusak ditolak.")

        unchanged_counts = dict(manifest["tables"]["units"], sha256="0" * 64)
        manifest["tables"]["units"] = unchanged_counts
        baseline.write_text(json.dumps(manifest))
        check_failure(restored_config, baseline, "Cadangan berbeda pada tabel units.")
        print("PASS manifest mendeteksi isi tabel berbeda dengan jumlah baris yang sama.")


if __name__ == "__main__":
    main()

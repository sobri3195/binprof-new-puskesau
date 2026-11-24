"""Exercise the built cPanel ZIP with a disposable MariaDB config and PHP CLI."""
import os
from contextlib import contextmanager
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request
import zipfile

PHP = os.environ.get("BINPROF_TEST_PHP", "php")
HTTP = urllib.request.build_opener(urllib.request.ProxyHandler({}))


def request(port, path):
    try:
        response = HTTP.open(f"http://127.0.0.1:{port}{path}", timeout=2)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read()


@contextmanager
def server(public, env, router=None, php_options=()):
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        port = listener.getsockname()[1]
    args = [PHP, "-d", "display_errors=1", *php_options, "-S", f"127.0.0.1:{port}", "-t", str(public)]
    if router:
        args.append(str(router))
    with tempfile.TemporaryFile() as log:
        process = subprocess.Popen(args, cwd=public, env=env, stdout=log, stderr=log)
        try:
            for _ in range(50):
                try:
                    request(port, "/")
                    break
                except urllib.error.URLError:
                    if process.poll() is not None:
                        break
                    time.sleep(0.05)
            else:
                raise RuntimeError("PHP server did not start")
            assert process.poll() is None, "PHP server exited before startup"
            yield port
        finally:
            process.terminate()
            process.wait(timeout=5)


def cli(project, env, script, *roots, expected=0, php_options=()):
    result = subprocess.run([PHP, *php_options, str(project / "cli" / script), *map(str, roots)],
                            env=env, capture_output=True, text=True, check=False, timeout=10)
    assert result.returncode == expected, (script, result.returncode, result.stdout, result.stderr)
    return result.stdout + result.stderr


def main(bundle):
    base_config = Path(os.environ["BINPROF_CONFIG"]).resolve(strict=True)
    with tempfile.TemporaryDirectory(prefix="binprof-deployment-") as directory:
        work = Path(directory)
        with zipfile.ZipFile(bundle) as archive:
            entries = set(archive.namelist())
            assert {"binprofkes/config/example.php", "binprofkes/app/Services/PrivateLayout.php",
                    "binprofkes/database/binprofkes.sql", "binprofkes/public/.htaccess"} <= entries
            forbidden = {"local.php", "storage", "keys.json", "node_modules", "tests", "demo.sql"}
            assert all(not forbidden.intersection(Path(entry).parts) for entry in entries)
            assert archive.testzip() is None
            archive.extractall(work)
        project = work / "binprofkes"
        mode_a = project / "public"
        shared_root = work / "public_html"
        mode_b = shared_root / "binprof"
        mode_b.mkdir(parents=True)
        for name in ("index.php", ".htaccess"):
            shutil.copy2(mode_a / name, mode_b / name)
        shutil.copytree(mode_a / "assets", mode_b / "assets")

        config = work / "fixture.php"
        config.write_text("""<?php
return array_replace(require getenv('BINPROF_BASE_CONFIG'), [
    'storage' => getenv('BINPROF_TEST_STORAGE'),
    'keys' => getenv('BINPROF_TEST_KEYS'),
    'require_https' => getenv('BINPROF_TEST_REQUIRE_HTTPS') === '1',
    'clean_urls' => getenv('BINPROF_TEST_CLEAN_URLS') === '1',
    'base_path' => '',
]);
""")
        state = work / "application-state"
        keyring = state / "keys.json"
        env = dict(os.environ, BINPROF_CONFIG=str(config), BINPROF_BASE_CONFIG=str(base_config),
                   BINPROF_TEST_STORAGE=str(state), BINPROF_TEST_KEYS=str(keyring),
                   BINPROF_TEST_REQUIRE_HTTPS="1", BINPROF_TEST_CLEAN_URLS="0")
        env.pop("BINPROF_ROOT", None)
        cli(project, env, "init-storage.php", mode_a)
        cli(project, env, "preflight.php", mode_a)
        cli(project, env, "preflight.php", mode_b, shared_root)
        print("PASS inisialisasi dan preflight paket mode A dan B.")

        unsafe_key = mode_b / "future-key.json"
        unsafe = dict(env, BINPROF_TEST_KEYS=str(unsafe_key))
        assert "keyring berada di bawah document root" in cli(project, unsafe, "init-storage.php", mode_b, expected=1)
        assert not unsafe_key.exists()
        unsafe_storage = mode_b / "future-storage"
        unsafe = dict(env, BINPROF_TEST_STORAGE=str(unsafe_storage))
        cli(project, unsafe, "init-storage.php", mode_b, expected=1)
        assert not unsafe_storage.exists()
        assert "keyring berada di bawah document root" in cli(
            project, env, "preflight.php", mode_b, state, expected=1)
        public_upload = ("-d", f"upload_tmp_dir={mode_b}")
        assert "direktori sementara unggahan berada di bawah document root" in cli(
            project, env, "preflight.php", mode_b, expected=1, php_options=public_upload)
        print("PASS inisialisasi menolak keyring/penyimpanan publik, alias berbahaya, dan temp unggahan publik.")

        probe = project / "config" / "privacy-fixture.txt"
        sentinel = b"BINPROF_PRIVATE_LAYOUT_SYNTHETIC"
        probe.write_bytes(sentinel)
        with server(mode_a, env, mode_a / "router.php") as port:
            assert request(port, "/login")[0] == 403
        loopback = dict(env, BINPROF_TEST_REQUIRE_HTTPS="0")
        for public, runtime, login, router in [
            (mode_a, dict(loopback, BINPROF_TEST_CLEAN_URLS="1"), "/login", mode_a / "router.php"),
            (mode_b, dict(loopback, BINPROF_ROOT=str(project)), "/index.php?r=login", None),
        ]:
            with server(public, runtime, router) as port:
                status, body = request(port, login)
                assert status == 200 and b"Masuk ke akun" in body
                assert request(port, "/assets/app.css")[0] == 200
                for path in ("/config/privacy-fixture.txt", "/database/binprofkes.sql", "/storage/keys.json"):
                    code, content = request(port, path)
                    assert code == 404 and sentinel not in content
        print("PASS login, aset, kebijakan HTTPS, dan probe privat di kedua layout lokal.")

        target = mode_b / "public-fixture"
        target.mkdir()
        for name in ("sessions", "documents"):
            redirected = work / f"redirected-{name}"
            redirected.mkdir()
            (redirected / name).symlink_to(target, target_is_directory=True)
            (redirected / ("documents" if name == "sessions" else "sessions")).mkdir()
            runtime = dict(loopback, BINPROF_ROOT=str(project), BINPROF_TEST_STORAGE=str(redirected))
            assert f"{'sesi' if name == 'sessions' else 'dokumen'} berada di bawah document root" in cli(
                project, runtime, "preflight.php", mode_b, expected=1)
            with server(mode_b, runtime) as port:
                code, body = request(port, "/index.php?r=login")
                assert code == 500 and b"Layanan sedang bermasalah" in body and str(work).encode() not in body
            assert not list(target.iterdir())
        runtime = dict(loopback, BINPROF_ROOT=str(work / "missing-private-bootstrap"))
        with server(mode_b, runtime) as port:
            code, body = request(port, "/index.php?r=login")
            assert code == 500 and b"Layanan sedang bermasalah" in body and b"missing-private-bootstrap" not in body
        runtime = dict(loopback, BINPROF_ROOT=str(project))
        with server(mode_b, runtime, php_options=public_upload) as port:
            assert request(port, "/index.php?r=login")[0] == 500
        print("PASS aplikasi menolak sesi/dokumen/temp unggahan publik dan menyamarkan galat bootstrap.")


if __name__ == "__main__":
    main(Path(sys.argv[1]))

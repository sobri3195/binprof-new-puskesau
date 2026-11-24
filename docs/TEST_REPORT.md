# Bukti pengujian lokal

Lingkungan uji cloud Debian 13 menggunakan PHP CLI 8.4.28, MariaDB 11.8.9, dan Chromium 156.0.8078.79. Web lokal memakai server pengembangan PHP. Leaflet 1.9.4 dan world-atlas 2.0.2 dikunci di `vendor-assets/package-lock.json`. Semua data pengujian sintetis.

| Pemeriksaan | Hasil |
|---|---|
| Impor instalasi dan demo | Skema kosong diimpor dengan klien MariaDB; dua satuan dan empat referensi demo dimuat terpisah. |
| Migrasi 002 berdata | Impor baseline 001, muat dua satuan, jalankan migrasi 002, periksa kedua satuan dan indeks audit. |
| Bootstrap | Dua akun awal berbeda berhasil dibuat. Pemanggilan kedua menolak dengan status keluar gagal dan pesan terkunci. |
| Integrasi HTTP | 36 pemeriksaan bernama lulus melalui handler dan MariaDB nyata. Cakupan termasuk CSRF, snapshot, persetujuan sendiri, konflik versi, rollback duplikat, cakupan satuan/modul, unggah, dokumen, pendidikan, penghargaan, CSV, kasus, mutasi, arsip, pengingat, rate limit, filter dan ekspor rekap, serta integritas master. |
| Integrasi CLI | Tes akhir bulan 2027 dan tahun kabisat 2028, lease aktif/kedaluwarsa, mutasi efektif, dan invalidasi perkiraan lulus. |
| Konkurensi | Dua proses verifikasi menghasilkan tepat satu keputusan dan satu penghargaan. Pada basis data restore terpisah, kegagalan penulisan notifikasi yang diinjeksi menggulung balik batch, dua proses retry selesai dengan tiga notifikasi, dan ulang setelah commit tidak menambah baris. |
| Chromium | 1440×900 dan 390×844 pada fallback serta clean URL. Login, skip link, navigasi, rekap, fokus dan reset peta serta tautan personel di marker lulus. Marker dan daftar personel tetap dapat dipakai saat permintaan latar lokal sengaja dihentikan oleh runner. |
| Pemulihan | Dump dan impor ke database lain, lalu salin dokumen dan keyring. SHA-256 dokumen cocok, nomor 0000123 dapat didekripsi, dua personel dan dua relasi pihak sesuai. Rangkaian lokal sekitar 0,7 detik termasuk pemeriksaan migrasi terpisah. |
| Privasi direktori | Permintaan lokal untuk jalur SQL, config, penyimpanan kunci dan cron menerima 404 melalui document root yang diuji. |
| Sintaks | 46 berkas PHP lulus lint. JavaScript peta lulus `node --check`. |

## Kinerja sintetis

Dataset MariaDB berisi 10.000 personel, 100 satuan, dan 50.000 baris riwayat. Fixture langsung membangun kolom yang dibutuhkan daftar dan agregat. Benchmark tidak mengekspor atau membuka profil lengkap. Empat klien paralel mengirim 60 permintaan per endpoint pada server PHP berpekerja. Lingkungan melaporkan 5 CPU logis dan sekitar 33 GiB memori host terlihat. Kedua endpoint diuji dengan filter Dikum dan Dikmil.

| Endpoint | p95 | Validasi respons |
|---|---:|---|
| Daftar personel, 25 baris per halaman | 148,87 ms | Nama fixture yang diharapkan ada pada respons. |
| API agregat peta | 143,55 ms | Jumlah hasil tepat 10.000 walaupun masing-masing memiliki lima riwayat. |

Angka mengukur HTTP lokal dan tidak mencakup jaringan pengguna serta penyedia tile eksternal. Kapasitas cPanel perlu diukur pada host yang dipilih.

## Reproduksi

Impor skema dan `demo.sql` ke database sintetis baru. Siapkan `BINPROF_CONFIG` privat dengan `require_https=false` untuk loopback dan jalankan `php cli/init-storage.php`. Gunakan username `demo-admin` dan `demo-grants` dengan password fixture yang tertera di `tests/http_flow.py` saat bootstrap. Jika PHP perlu konfigurasi ekstensi khusus, set `BINPROF_TEST_PHP` ke path wrapper yang sesuai. Fixture akun uji hanya dibuat pada database baru lewat skrip HTTP.

```sh
php -S 127.0.0.1:8710 -t public public/router.php
# Terminal terpisah, dengan BINPROF_CONFIG yang menunjuk ke basis data sintetis
BINPROF_TEST_PHP="$(command -v php)" python3 tests/http_flow.py
BINPROF_TEST_PHP="$(command -v php)" python3 tests/concurrency.py
python3 tests/browser_smoke.py
find app cli config public tests -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/assets/map.js
```

Untuk clean URL, jalankan server yang sama dengan `clean_urls=true`, lalu arahkan skrip browser ke port tersebut lewat `BINPROF_TEST_URL`. Uji retry menargetkan database terpisah bernama `binprof_restore` hasil restore dari fixture integrasi. Untuk benchmark, impor skema, demo dan `tests/perf_fixture.sql` ke database MariaDB kosong, buat akun sintetis `demo-bench` dengan peran Pimpinan dan scope 100 satuan, lalu jalankan server dengan `PHP_CLI_SERVER_WORKERS=4`. Arahkan `tests/benchmark.py` ke port server tersebut.

## Pemeriksaan tertunda

MySQL, PHP 8.5, cron cPanel nyata, verifikasi TLS/cookie Secure melalui host, root/alias semua domain, kuota, lisensi tile pihak ketiga, antivirus, dan UAT pemilik layanan belum diuji. Respons lokal memakai HTTP loopback. Pemutusan proses paksa tepat setelah commit tidak disimulasikan. Pengulangan setelah commit dan rollback saat kesalahan tulis diuji.

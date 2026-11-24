# Bukti pengujian lokal

Lingkungan uji cloud Debian 13 menggunakan PHP CLI 8.4.28, MariaDB 11.8.9, dan Chromium 156.0.8078.79. Web lokal memakai server pengembangan PHP. Leaflet 1.9.4 dan world-atlas 2.0.2 dikunci di `vendor-assets/package-lock.json`. Semua data pengujian sintetis.

| Pemeriksaan | Hasil |
|---|---|
| Impor instalasi dan demo | Skema kosong diimpor dengan klien MariaDB; dua satuan dan empat referensi demo dimuat terpisah. |
| Migrasi berdata | Migrasi 001→002 mempertahankan dua satuan dan membuat indeks audit. Migrasi 002→003 pada salinan data audit mempertahankan empat dokumen aktif dan semua tabel lain selain metadata skema yang memang berubah. |
| Bootstrap | Dua akun awal berbeda berhasil dibuat. Pemanggilan kedua menolak dengan status keluar gagal dan pesan terkunci. |
| Integrasi HTTP | 42 pemeriksaan bernama lulus melalui handler dan MariaDB nyata. Cakupan termasuk CSRF, snapshot, persetujuan sendiri, konflik versi, rollback duplikat, cakupan satuan/modul, unggah, dokumen, pendidikan, penghargaan, CSV, kasus, mutasi, arsip, pengingat, rate limit, filter dan ekspor rekap, integritas master, pengajuan atomik dari formulir, isolasi riwayat draf, serta pencabutan grant unduh lampiran kasus historis. |
| Integrasi CLI | Tes akhir bulan 2027 dan tahun kabisat 2028, lease aktif/kedaluwarsa, mutasi efektif, dan invalidasi perkiraan lulus. |
| Regresi audit P0 | 15 pemeriksaan lulus. Cakupan mencakup rebase pada kolom berbeda, pilihan bentrok, formulir lama dari tab lain, kelengkapan draf, rentang tanggal di dashboard, dasar perkiraan, jenis nomor nonaktif, Dikmil, batas POST 413, penggantian dan pelepasan lampiran, serta 41 login sah berturut-turut dengan pembatasan kegagalan tetap aktif. |
| Regresi P1 | 18 pemeriksaan lulus. Cakupan termasuk XLSX dibaca melalui openpyxl, teks PDF diekstrak dengan pdftotext, hak ekspor termasuk grant kasus, dry-run CSV, deteksi duplikat dan rollback batch, persetujuan profil dan riwayat, serta hitungan jabatan menurut cakupan, status dan bulan. Migrasi 003→004 mempertahankan isi 31 tabel, termasuk tujuh lampiran privat. |
| Konkurensi | Dua proses verifikasi menghasilkan tepat satu keputusan dan satu penghargaan. Pada basis data restore terpisah, kegagalan penulisan notifikasi yang diinjeksi menggulung balik batch, dua proses retry selesai dengan tiga notifikasi, dan ulang setelah commit tidak menambah baris. |
| Chromium | 1440×900 dan 390×844 pada fallback serta clean URL. Login, skip link, navigasi, rekap, fokus dan reset peta serta tautan personel di marker lulus. Marker dan daftar personel tetap dapat dipakai saat permintaan latar lokal sengaja dihentikan oleh runner. Formulir pengajuan mempertahankan input saat validasi gagal, lalu mengirim nilai koreksi pada percobaan berikutnya. |
| Pemulihan | Dump dan impor ke database terpisah dengan dokumen dan keyring yang dipulihkan. Manifest skema 004 cocok pada 34 tabel dan tujuh lampiran, termasuk dua lampiran yang dilepas pada draf. Relasi, dekripsi dan digest identitas serta checksum dokumen lulus. Pengukuran 450 ms sebelumnya memakai fixture empat lampiran pada skema 002. |
| Kegagalan pemulihan | Uji menemukan lampiran hilang atau rusak, keyring salah, digest identitas berbeda, kunci historis yang hilang setelah rotasi, dan data berbeda meski jumlah baris sama. Uji terpisah mengubah satu nilai pada database hasil restore dan memeriksa kegagalan perbandingan, lalu mengembalikan nilai dan memperoleh kecocokan. |
| Privasi direktori | Paket diekstrak dan diuji pada layout lokal A dan B. Halaman login dan aset dapat diakses. Probe sintetis, SQL dan jalur kunci mendapat 404. Preflight menolak alias yang mencakup penyimpanan atau direktori sementara unggahan, dan aplikasi menolak symlink sesi/dokumen ke direktori publik. Galat bootstrap mengembalikan pesan umum. |
| Sintaks | 56 berkas PHP lulus lint. JavaScript peta lulus `node --check`. |

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
php -d display_errors=0 -S 127.0.0.1:8710 -t public public/router.php
# Terminal terpisah, dengan BINPROF_CONFIG yang menunjuk ke basis data sintetis
BINPROF_TEST_PHP="$(command -v php)" python3 tests/http_flow.py
BINPROF_TEST_PHP="$(command -v php)" python3 tests/concurrency.py
python3 tests/browser_smoke.py
python3 tests/p0_regression.py
python3 tests/p1_regression.py
find app cli config public tests vendor-exports -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/assets/map.js
tools/package-release.sh
BINPROF_TEST_PHP="$(command -v php)" python3 tests/deployment_layout.py dist/binprofkes-cpanel.zip
```

Regresi P1 memakai ekstensi PHP `zip`, paket Python `openpyxl`, dan perintah `pdftotext` dari Poppler.

Untuk clean URL, jalankan server yang sama dengan `clean_urls=true`, lalu arahkan skrip browser ke port tersebut lewat `BINPROF_TEST_URL`. Uji retry menargetkan database terpisah bernama `binprof_restore` hasil restore dari fixture integrasi. Untuk benchmark, impor skema, demo dan `tests/perf_fixture.sql` ke database MariaDB kosong, buat akun sintetis `demo-bench` dengan peran Pimpinan dan scope 100 satuan, lalu jalankan server dengan `PHP_CLI_SERVER_WORKERS=4`. Arahkan `tests/benchmark.py` ke port server tersebut.

Pemeriksaan paket memakai server pengembangan PHP. Mode A diuji dengan URL bersih dan mode B dengan URL fallback. Kebijakan wajib HTTPS diperiksa melalui penolakan HTTP loopback.

Untuk mengulang uji restore, ambil dump fixture integrasi setelah web dan cron dihentikan. Impor ke database sintetis lain, salin direktori dokumen serta keyring, dan buat konfigurasi pemulihan dengan penyimpanan terpisah. Jalankan perintah berikut dengan `BINPROF_CONFIG` menunjuk ke fixture sumber.

```sh
BINPROF_RESTORE_CONFIG=/path/privat/restore.php \
    BINPROF_TEST_PHP="$(command -v php)" python3 tests/restore_verification.py
```

## Pemeriksaan tertunda

MySQL, PHP 8.5, cron cPanel nyata, verifikasi TLS/cookie Secure melalui host, root/alias semua domain, kuota, lisensi tile pihak ketiga, antivirus, dan UAT pemilik layanan belum diuji. Respons lokal memakai HTTP loopback. Pemutusan proses paksa tepat setelah commit tidak disimulasikan. Pengulangan setelah commit dan rollback saat kesalahan tulis diuji.

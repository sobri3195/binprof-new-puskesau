# BINPROFKES

Aplikasi PHP native untuk administrasi personel. Operator menyusun perubahan, pusat memeriksa revisi, dan pimpinan melihat data terverifikasi berdasarkan cakupan satuan.

## Memulai

1. Siapkan PHP 8.4 dengan PDO MySQL, Fileinfo, Sodium dan Mbstring serta basis data MariaDB. Impor [database/binprofkes.sql](database/binprofkes.sql) ke basis data kosong.
2. Salin [config/example.php](config/example.php) ke lokasi privat dan atur `BINPROF_CONFIG`. Konfigurasi dapat ditempatkan di `config/local.php` bila direktori proyek privat.
3. Jalankan `php cli/init-storage.php` dan buat dua akun awal melalui `php cli/bootstrap-admin.php` sesuai panduan cPanel.
4. Arahkan document root ke `public/`. Untuk pengembangan lokal dengan basis data sintetis, tetapkan `require_https` ke `false` pada konfigurasi tersebut dan jalankan `php -S 127.0.0.1:8710 -t public public/router.php`. Untuk pemasangan aktif, pertahankan HTTPS.
5. Buat master, akun operator, akun pemeriksa, cakupan satuan, dan modul verifikasi. Isi data uji hanya dari `database/demo.sql` jika diperlukan.

## Panduan proyek

- [Instalasi cPanel](docs/INSTALL_CPANEL.md)
- [Operasional dan impor CSV](docs/OPERATIONS.md)
- [Cadangan dan pemulihan](docs/BACKUP_RESTORE.md)
- [Matriks akses](docs/ACCESS_MATRIX.md)
- [Kamus data](docs/DATA_DICTIONARY.md)
- [Keputusan implementasi](docs/DECISIONS.md)
- [Bukti uji](docs/TEST_REPORT.md) dan [status penerimaan](TASKS.md)

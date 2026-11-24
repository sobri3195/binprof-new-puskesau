# Instalasi BINPROFKES di cPanel

Paket berisi PHP native, SQL dan aset Leaflet lokal. PHP 8.4.28 dan MariaDB 11.8.9 diuji dalam lingkungan cloud lokal. Periksa cabang PHP yang dipelihara, versi server hosting, PDO MySQL, Fileinfo, Mbstring, Sodium, HTTPS, kuota, batas upload, PHP CLI, dan Cron Jobs.

## Membuat paket dari checkout

Jalankan `tools/package-release.sh` dari checkout Git. Hasil default adalah `dist/binprofkes-cpanel.zip` dari commit `HEAD`. Argumen opsional pertama menentukan lokasi ZIP dan argumen kedua menentukan commit atau tag. Paket memasukkan konfigurasi contoh, skema, aplikasi, CLI, panduan dan aset yang sudah dibuat. Gunakan commit yang berisi perubahan yang ingin dirilis.

## Penempatan

| Mode | Document root | Berkas privat |
|---|---|---|
| A | `/home/AKUN/binprof/public` | `/home/AKUN/binprof` di luar root semua domain lain. |
| B | `/home/AKUN/public_html/binprof` | `/home/AKUN/binprof_private`. Salin `index.php`, `.htaccess` dan `assets/` ke document root. Atur `BINPROF_ROOT` di PHP/FPM atau atur lokasi privat absolut pada `index.php`. |

1. Buat database dan akun lewat cPanel. Impor `database/binprofkes.sql` ke database kosong, menggunakan akun migrasi bila memungkinkan. Jalankan hanya migrasi yang belum tercatat untuk upgrade. Rilis ini memerlukan migrasi 003 pada instalasi yang masih memakai skema 002. Cadangkan dahulu karena DDL mesin ini melakukan commit implisit.
2. Salin `config/example.php` ke `config/local.php` privat. Isi DSN dan kredensial setempat. Gunakan path absolut untuk konfigurasi, penyimpanan dan keyring. Pertahankan `require_https=true`; aktifkan TLS di hosting. Isi `base_path` dengan prefiks URL seperti `/binprof` hanya bila aplikasi memang diakses lewat prefiks tersebut.
3. Jalankan `php cli/init-storage.php /path/root-subdomain /path/public_html`, lalu `php cli/preflight.php /path/root-subdomain /path/public_html`. Argumen pertama adalah direktori publik aplikasi. Sertakan setiap document root alias sebagai argumen tambahan. Kedua perintah memakai `public/` dari paket bila dijalankan tanpa argumen. Jaga hak akses sesi, kunci, log dan cadangan hanya untuk akun layanan.
4. Buat dua akun awal melalui stdin privat. Gunakan akun berbeda dan kata sandi baru yang kuat. Simpan JSON sementara di luar semua webroot dengan mode 0600. Hapus setelah berhasil. Jangan masukkan kata sandi ke argumen atau log.
5. Tetapkan `clean_urls=false` untuk fallback `index.php?r=...`. Gunakan `true` bila rewrite Apache aktif. Uji tautan, form dan aset setelah memilih mode.
6. Klik masuk dari browser, periksa cookie Secure/HttpOnly/SameSite, buat satu unit uji dan dua akun peran berbeda, serta jalankan satu pengajuan.
7. Pasang cron sesuai [panduan operasi](OPERATIONS.md). Jika terminal tidak tersedia, gunakan Cron Jobs sementara untuk menjalankan langkah CLI dengan stdin privat dan hapus entri bootstrap setelah berhasil.
8. Buat berkas dengan penanda sintetis di direktori privat. Uji URL yang sesuai dari subdomain, domain utama, dan setiap alias. Harapkan respons 404 atau 403 dan periksa bahwa respons tidak memuat penanda. Hapus probe setelah pemeriksaan. Periksa konfigurasi PHP/FPM secara terpisah dari CLI, terutama pengandar sesi berkas, direktori sementara unggahan dan batas unggahan. Atur `display_errors=Off` pada konfigurasi PHP/FPM agar peringatan yang terjadi sebelum aplikasi mulai masuk ke log server. Tetapkan `post_max_size` lebih besar dari batas berkas untuk mengakomodasi metadata multipart. Lakukan latihan pemulihan sebelum pemakaian aktif.

Struktur input bootstrap memakai dua objek dengan `username` dan `password`. Isi nilai di berkas privat melalui editor yang sesuai.

```json
{"admin":{"username":"","password":""},"case_manager":{"username":"","password":""}}
```

```sh
umask 077
/absolute/path/to/php /home/AKUN/binprof_private/cli/bootstrap-admin.php < /home/AKUN/private/bootstrap-input.json
```

Akun runtime memerlukan SELECT/INSERT/UPDATE/DELETE sesuai query aplikasi, dengan hak audit baca dan tambah saja jika konfigurasi grant per tabel tersedia. Hak DDL dicadangkan untuk akun instalasi. Jangan impor `demo.sql` ke produksi.

Aplikasi memeriksa jalur fisik konfigurasi, sesi, dokumen, SQL, CLI dan keyring terhadap document root permintaan. Preflight juga menerima root domain lain. Pengujian HTTP di setiap domain diperlukan untuk memeriksa aturan Apache dan pemetaan alias.

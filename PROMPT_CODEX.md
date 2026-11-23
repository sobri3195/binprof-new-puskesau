# Prompt Codex — Implementasi BINPROFKES PHP Native

Tanggal: 23 November 2026.

## Cara memakai

1. Ekstrak paket spesifikasi dan letakkan `brainstorming.md`, `PRD.md`, serta `TASKS.md` pada folder proyek yang dapat dibaca Codex.
2. Buka folder tersebut di Codex. Salin isi blok **PROMPT UTAMA** di bawah.
3. Bila proyek sudah mempunyai kode, gunakan folder proyek itu dan pertahankan pekerjaan yang sudah ada.
4. Prompt ini meminta pembangunan aplikasi dan SQL. Dokumen spesifikasi yang menyertainya belum merupakan aplikasi jadi.

## PROMPT UTAMA

```text
Anda adalah senior engineer PHP native yang bertanggung jawab membangun aplikasi BINPROFKES sampai dapat diperiksa dan dipasang pada shared hosting/cPanel.

HASIL YANG DIMINTA

Bangun aplikasi nyata berdasarkan brainstorming.md, PRD.md dan TASKS.md. Hasilnya harus mencakup kode PHP native, database/binprofkes.sql yang dapat diimpor, migrasi, dependency/aset lokal, alat cron, dokumentasi cPanel, serta bukti pengujian. Kerjakan implementasinya; jangan berhenti pada rencana, skeleton, mockup, atau contoh potongan kode.

1. BACA SUMBER DAN LINGKUNGAN

- Baca AGENTS.md yang berlaku dan instruksi pengguna terbaru.
- Temukan dan baca brainstorming.md, PRD.md dan TASKS.md secara lengkap sebelum menetapkan model data. PRD merinci keputusan brainstorming; TASKS mengatur urutan kerja. Jika ada konflik dengan keputusan pengguna, catat dan selesaikan sesuai instruksi pengguna, sambil melanjutkan pekerjaan independen yang tidak terpengaruh.
- Periksa struktur repositori, status perubahan, PHP, database, tool pengujian, dan dependency yang tersedia. Jangan menimpa pekerjaan pengguna atau memulai ulang proyek yang sudah berfungsi.
- Gunakan TASKS.md sebagai checklist aktual. Ada 40 tugas: 38 P0 wajib dan 2 P1, yaitu T23 dan T33. Ikuti dependensi, bukan sekadar nomor.
- Buat docs/DECISIONS.md dan catatan progres. Nyatakan asumsi teknis sebagai asumsi, bukan jawaban yang seolah-olah sudah diberikan pengguna.

2. KEPUTUSAN PRODUK YANG WAJIB DIPERTAHANKAN

- PHP native dengan database MySQL/MariaDB dan berkas SQL, untuk shared hosting/cPanel pada subdomain pengguna.
- Kategori Militer dan PNS, serta kategori tambahan yang dapat dikelola melalui master.
- Militer memakai NRP/pangkat/korps yang relevan; PNS memakai NIP/pangkat/golongan ruang yang relevan. Nomor kedinasan berupa teks, unik per jenis, dan mempertahankan nol depan.
- Satu profil personel memuat identitas, kedinasan, banyak Dikum, banyak Dikmil, penghargaan, kasus sesuai izin, perkiraan pensiun, serta dokumen.
- Kasus berarti disiplin, pelanggaran dan perkara hukum. Modul ini mempunyai penugasan dan izin tersendiri.
- Pensiun P0 berupa tanggal perkiraan yang diinput, diberi sumber/alasan dan diverifikasi, ditambah pengingat. Rumus otomatis hanya P1 setelah aturan resmi tervalidasi. Jangan mengarang batas usia pensiun.
- Operator tiap satuan mengisi; pusat memverifikasi.
- Halaman utama pimpinan: peta satuan/personel, ringkasan angka, filter dan pengingat penting.
- Gunakan lokasi satuan/penugasan. Tidak ada pelacakan GPS personel atau penggunaan alamat rumah sebagai marker.

3. BATAS DAN ASUMSI AWAL

- Perkiraan pensiun manual terverifikasi menjadi baseline; rumus P1 tidak menghalangi P0.
- Usulan konfigurasi awal: notifikasi dalam aplikasi, tenggat 12/6/3/1 bulan; unggah PDF/JPG/PNG maksimal 5 MiB; zona waktu tampilan Asia/Jakarta; timestamp konsisten UTC dan tanggal kalender DATE. Jadikan konfigurasi yang dapat diubah.
- CSV adalah ekspor wajib. XLSX/PDF, email/WhatsApp, impor massal lama, dan rumus pensiun merupakan perluasan sesuai PRD.
- Rekam medis, rikkes, kasus klinis, chatbot dan integrasi eksternal belum termasuk cakupan ini.
- Nama subdomain final, aset resmi, data satuan nyata, koordinat, aturan pensiun, serta kemampuan hosting belum diberikan. Gunakan konfigurasi, slot aset yang rapi, dan data sintetis berlabel DATA DEMO. Data produksi awal boleh kosong dengan empty state yang jelas.
- Referensi tampilan: https://binprof.puskesau.com/. Jika dapat dibuka secara sah, amati bagian publik yang relevan. Jangan menebak kredensial, melewati login, menguji serangan pada situs itu, atau menyalin akun/data personel dari bundle publik. Tandai rekonstruksi bagian yang belum dapat dilihat.

4. ARSITEKTUR DAN KOMPATIBILITAS

- Gunakan PHP native tanpa framework backend/frontend besar. Pisahkan controller, service, repository, policy, middleware dan view.
- Gunakan PHP yang masih dipelihara dan sesuai hosting; target awal kompatibilitas 8.4/8.5. Catat versi yang benar-benar diuji. Pilih versi database dan fitur SQL berdasarkan mesin aktual, bukan dugaan.
- Gunakan PDO prepared statements untuk nilai, allowlist untuk identifier/sort, InnoDB, utf8mb4, FK dan indeks.
- Pustaka spesifik yang terpelihara diperbolehkan untuk kebutuhan seperti kriptografi atau peta. Jangan membuat algoritme kriptografi sendiri.
- Siapkan dependency lokal beserta lockfile/lisensi. Composer/Node dapat digunakan pada tahap persiapan lokal bila berguna, tetapi tidak wajib tersedia saat aplikasi berjalan di hosting.
- Aplikasi hosting tidak bergantung pada Node.js, Docker, Redis, daemon atau WebSocket. Tugas periodik menggunakan cron PHP CLI.
- Sediakan router allowlist, satu URL helper, serta dukungan URL bersih dan fallback index.php?r=... untuk link, form, AJAX, pagination dan aset.

5. PENEMPATAN BERKAS CPANEL

Siapkan dua mode sesuai PRD:

A. Jika document root khusus diizinkan, arahkan subdomain hanya ke public/ dan tempatkan seluruh berkas privat di luar semua document root pada akun.
B. Jika document root dibatasi di public_html, letakkan hanya front controller/aset di direktori subdomain dan simpan aplikasi, konfigurasi, SQL, storage, dependency privat dan alat CLI di direktori di luar public_html.

- Path dan domain contoh harus dapat dikonfigurasi. Periksa akses melalui domain utama dan alias juga.
- SQL, konfigurasi, sesi, log, upload, export, backup dan kunci tidak boleh tersedia sebagai URL publik. Jangan mengandalkan nama sulit ditebak atau sekadar satu aturan deny yang belum diuji.
- Buat preflight untuk versi/ekstensi PHP, database, kuota, HTTPS, upload, CLI/cron dan document root. Periksa fitur JSON/kolasi hanya sesuai engine yang dipakai.
- Bootstrap administrator/pejabat awal pemberi izin kasus dilakukan satu kali melalui jalur CLI terkontrol, lalu dikunci. Tidak ada password universal dalam SQL. Jika tidak ada terminal interaktif, dokumentasikan cara sekali jalan melalui cron PHP CLI dengan input privat sementara; secret tidak masuk argumen atau output dan input sementara dihapus setelah berhasil.

6. INTI DATA DAN VERIFIKASI

- Gunakan ID personel tetap. Kategori dan status dinas berbeda. Riwayat penempatan serta pendidikan/penghargaan memakai tabel relasional tersendiri.
- Simpan tahun pendidikan sebagai tahun jika tanggal lengkap tidak diketahui. Jangan membuat tanggal fiktif.
- Draf dan pengajuan tidak menimpa data terverifikasi. Snapshot submitted tidak boleh diedit; perbaikan membuat revisi baru.
- Implementasikan Draf, Diajukan, Dikembalikan, Disetujui dan Dibatalkan beserta transisi valid.
- Pemeriksa melihat perbedaan sebelum/sesudah dan lampiran terkait. Pemohon tidak boleh menyetujui pengajuannya sendiri meskipun memiliki beberapa peran.
- Persetujuan mengunci revisi/objek, memeriksa ulang akun, izin, cakupan, versi dasar dan dokumen, kemudian menerapkan perubahan, keputusan, audit dan versi dalam transaksi yang sama.
- Konflik revisi atau persetujuan bersamaan tidak boleh mengakibatkan lost update. Tampilkan konflik yang dapat diperbaiki pengguna.
- Tanggal efektif mutasi/status masa depan diterapkan pada waktunya melalui proses terjadwal yang aman; riwayat dan akses satuan lama dihitung ulang.
- Arsip/pemulihan mengikuti verifikasi dan mempertahankan relasi. Lewatnya perkiraan pensiun tidak mengubah status resmi atau mengarsipkan otomatis.

7. OTORISASI DAN KASUS

- Terapkan deny by default di server pada halaman, API, pencarian, dokumen, cetak, notifikasi dan ekspor. Query sudah scoped sebelum agregasi/pagination.
- Peran umum tidak otomatis memberikan hak kasus. Case grant membatasi objek dan tindakan; akses kasus tidak membuka seluruh profil pihak terkait.
- Pisahkan read/write/submit/verify/download/export serta pemberian grant. Pengguna tidak dapat memberikan izin sensitif kepada diri sendiri.
- Penciptaan kasus memerlukan capability dan satuan penanggung jawab yang sah; penugasan awal pencipta sempit pada kasus baru, tidak memberikan akses ke kasus lain.
- Satu kasus dapat terhubung ke beberapa personel dan mempunyai perkembangan, dokumen serta keputusan bersumber. Status penanganan terpisah dari status verifikasi.
- Jangan bocorkan judul, jumlah, keberadaan kasus, kronologi atau lampiran kepada pengguna tanpa izin lewat badge, marker, respons API, autocomplete, error, cache, notifikasi atau ekspor.
- Cek pencabutan izin pada sesi aktif serta pembacaan notifikasi/dokumen lama. Administrator teknis tidak otomatis mempunyai hak membaca isi kasus melalui aplikasi.
- Aplikasi mencatat informasi dan keputusan; tidak menetapkan kesalahan atau sanksi otomatis.

8. KEAMANAN APLIKASI DAN DOKUMEN

- Hash/verifikasi password memakai API PHP yang didukung, rotasi sesi login, cookie Secure/HttpOnly/SameSite, timeout, pembatasan login dan mekanisme pencabutan akses.
- Lindungi seluruh mutasi dengan CSRF; GET tidak mengubah keadaan. Terapkan validasi/allowlist input, escaping sesuai konteks, header keamanan dan CSP yang sesuai asal aset/peta.
- Jangan simpan otoritas login/role pada localStorage. Jangan sertakan quick login/bypass produksi.
- Unggahan diperiksa ukuran, ekstensi, MIME/struktur; nama acak; path privat; tidak executable; unduh selalu melalui policy. Catat pemeriksaan sebenarnya jika antivirus tidak tersedia.
- Lampiran draf hanya untuk pemohon/pemeriksa berwenang. Lampiran terverifikasi merujuk revisi yang disetujui. Penggantian membuat versi baru, tidak menimpa berkas lama.
- Lindungi isi kasus dan identitas sensitif dengan mekanisme enkripsi terpelihara dan kunci berversi di luar database/webroot; sediakan pemulihan kunci. Validasi dukungan ekstensi saat preflight.
- Audit mencatat perubahan, persetujuan, baca kasus, unduh dan ekspor tanpa password/token/kronologi mentah pada log umum. Riwayat sensitif mempunyai policy tersendiri.

9. PENSIUN, PENGINGAT DAN CRON

- Perkiraan mempunyai sumber, versi, metode, pemeriksa dan status validasi. Data dasar belum cukup harus menghasilkan keadaan belum ditetapkan/belum dapat dihitung.
- Hanya perkiraan terverifikasi yang masih berlaku menghasilkan pengingat baru. Perubahan dasar menandai perlu tinjau.
- Hitung tenggat dengan bulan kalender dan penjepitan akhir bulan; uji 31 Maret ke Februari pada tahun biasa/kabisat.
- Deduplikasi menggunakan unique key database yang mengikat personel, versi perkiraan/aturan, tenggat dan penerima. Pemuatan ulang dan cron ulang tidak menambah pesan duplikat.
- Pada aktivasi terlambat/jeda job, buat satu pengingat susulan paling relevan sesuai PRD, bukan seluruh tenggat yang sudah lewat.
- Koreksi perkiraan menggantikan jadwal lama secara terkontrol. Perkiraan terlewati hanya memicu konfirmasi, tidak mengubah status dinas.
- Gunakan lock bermasa berlaku, batch terbatas, transaksi penciptaan notifikasi/penerima, serta retry aman setelah crash sebelum/sesudah commit.
- Skrip cron menolak eksekusi web, tidak menaruh secret pada URL/argumen/output, dan mencatat hasil job. Sediakan alternatif pemeriksaan manual berizin jika penjadwalan belum tersedia; jelaskan keterbatasan waktunya.

10. DASHBOARD PIMPINAN, PETA DAN CSV

- Dashboard awal menampilkan marker satuan, kartu jumlah aktif terverifikasi/Militer/PNS/satuan, perkiraan pensiun dalam rentang terlihat, serta antrean sesuai role.
- Filter satuan, kategori, profesi, pangkat/golongan, Dikum/Dikmil dan rentang perkiraan harus sinkron pada kartu/marker/tabel/CSV.
- Hitung orang unik; beberapa pendidikan tidak menggandakan jumlah personel. Definisi populasi dan waktu pembaruan terlihat.
- Satuan tanpa koordinat tetap muncul di tabel dan dijelaskan jumlahnya; tidak mengarang koordinat. Kegagalan basemap tidak mematikan tabel/pencarian.
- Marker membawa agregat minimal. Nama/identitas personel dimuat setelah aksi detail yang diotorisasi. Jangan menyisipkan seluruh data personel ke HTML/JavaScript peta.
- Jika memakai cache, ikat pada izin/cakupan/filter/versi dan buktikan invalidasi saat izin dicabut; cache boleh ditunda bila belum aman.
- Gunakan Leaflet stabil lokal; basemap berlisensi/configurable. Tidak mengunduh massal tile publik untuk offline dan tidak menghapus atribusi yang diwajibkan.
- CSV memakai filter/kolom/cakupan yang sama, izin ekspor tersendiri, perlindungan formula, pengendalian ukuran dan audit. Pertahankan digit nomor kedinasan di dalam berkas dan sediakan petunjuk impor sebagai teks; pembukaan CSV otomatis dapat menafsirkan ulang angka panjang. Jangan memakai formula untuk memaksa format identitas.

11. SQL, PENGUJIAN DAN BUKTI

- database/binprofkes.sql harus sinkron dengan skema akhir. Sediakan instalasi kosong, migrasi berversi, master dasar dan demo terpisah. Jangan DROP database berdata sebagai cara upgrade.
- Jalankan impor nyata dan migrasi pada engine yang tersedia. Upayakan lingkungan database uji yang sesuai melalui tool yang tersedia. Jangan mengklaim kedua MySQL/MariaDB diuji bila hanya satu.
- Implementasikan pengujian integrasi untuk batas lintas satuan, perubahan ID, case grant, self-approval, CSRF/mass assignment, lampiran draf, pencabutan scope/notifikasi/cache, dan CSV.
- Uji transaksi rollback, stale version, persetujuan bersamaan, kasus beberapa pihak, pendidikan bertanggal parsial, hitung orang unik, cron concurrency/crash, akhir bulan/kabisat dan mutasi efektif.
- Uji perilaku melalui handler/database nyata bila tersedia; jangan hanya membuat tes yang menyalin struktur implementasi atau mengasumsikan HTTP 200 berarti data benar.
- Lakukan lint/smoke/UI yang sesuai, pengukuran kinerja dengan dataset dan beban dinyatakan, serta restore cadangan ke lingkungan terpisah.
- Cek direktori privat dengan probe tanpa rahasia pada domain/alias yang tersedia. Jangan sengaja mengunduh konfigurasi nyata sebagai tes.
- Catat perintah, versi, hasil aktual dan pemeriksaan yang belum bisa dilakukan pada docs/TEST_REPORT.md. Perbaiki kegagalan sebelum menandai tugas selesai. Jangan menulis hasil palsu atau menyebut target performa sebagai hasil ukur.

12. HASIL AKHIR DAN CARA BEKERJA

Hasil minimum:
- Aplikasi lengkap beserta aset/dependency lokal.
- database/binprofkes.sql dan migrations/.
- Konfigurasi contoh tanpa secret, bootstrap satu kali, serta skrip cron.
- docs/INSTALL_CPANEL.md, OPERATIONS.md, BACKUP_RESTORE.md, ACCESS_MATRIX.md, DATA_DICTIONARY.md, DECISIONS.md dan TEST_REPORT.md.
- TASKS.md yang diperbarui sesuai bukti, catatan progres/resume, serta paket rilis yang tidak memuat secret/data produksi/log/cadangan.

Kerjakan tugas P0 menurut dependensi dan teruskan sampai selesai. Tidak perlu meminta konfirmasi untuk pilihan implementasi rutin yang sudah tercakup, pembuatan berkas lokal, atau perbaikan setelah tes. Gunakan asumsi PRD untuk rincian yang belum diberikan; jangan mengarang data instansi atau aturan hukum.

Jika satu kemampuan lingkungan belum tersedia, catat hambatan secara spesifik dan lanjutkan pekerjaan independen. Jangan menandai tugas selesai tanpa bukti. Jika konteks/sesi terbatas, simpan progres aktual dan langkah berikutnya sehingga dapat dilanjutkan tanpa mengulang dari awal.

Laporan akhir menyebut hasil yang berfungsi, berkas utama, perintah dan hasil uji aktual, lingkungan yang diuji, serta keterbatasan material. Pembangunan dan paket lokal harus selesai sebelum meminta instruksi deployment. Jangan mengubah server aktif, mengimpor data produksi atau menerbitkan aplikasi tanpa instruksi deployment dari pengguna.

Mulai dengan pemeriksaan sumber/lingkungan, catat keputusan awal, lalu bangun tugas P0 pertama dan lanjutkan implementasi.
```

## Prompt lanjutan bila sesi terputus

```text
Lanjutkan implementasi BINPROFKES pada folder proyek ini. Baca AGENTS.md yang berlaku, PRD.md, TASKS.md, docs/DECISIONS.md, catatan progres dan keadaan berkas aktual. Jangan mulai ulang atau menganggap checklist selesai tanpa bukti. Pilih tugas P0 berikutnya yang dependensinya terpenuhi, selesaikan implementasi dan pengujiannya, lalu lanjutkan sampai seluruh P0 selesai. T23 dan T33 merupakan P1. Pertahankan pekerjaan pengguna, simpan progres yang dapat dilanjutkan, dan laporkan hasil aktual serta hambatan yang tersisa. Deployment server aktif menunggu instruksi deployment pengguna.
```

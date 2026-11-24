# TASKS — Implementasi BINPROFKES PHP Native

Versi: 1.0  
Tanggal: 23 November 2026  
Dasar: `PRD.md` dan `brainstorming.md`.  
Status implementasi dan batas verifikasi dicatat pada [docs/PROGRESS.md](docs/PROGRESS.md) dan [docs/TEST_REPORT.md](docs/TEST_REPORT.md). Centang menunjukkan verifikasi lokal yang tercatat.

## 1. Cara menggunakan daftar tugas

- P0 wajib untuk rilis pertama. P1 disiapkan untuk pengembangan berikutnya dan tidak menghalangi penyelesaian P0.
- Ada 40 tugas: 38 P0 dan 2 P1. T23 dan T33 merupakan P1.
- Ikuti dependensi, bukan nomor semata. T31/T32 dapat dikerjakan sebelum pekerjaan peta yang memerlukannya.
- Ubah `[ ]` menjadi `[x]` setelah hasil dan verifikasi tugas terpenuhi. Tambahkan path berkas, perintah aktual, hasil, serta batas pemeriksaan pada catatan progres.
- Catat asumsi dan keputusan dalam `docs/DECISIONS.md`; jangan mengubah pilihan pengguna secara diam-diam.
- Lakukan pengujian aturan penting pada tahap pembuatannya. T34–T37 menggabungkan bukti rilis, bukan alasan menunda seluruh pengujian sampai akhir.
- Pemeriksaan yang membutuhkan akses hosting dicatat menunggu akses, tanpa menghambat pekerjaan lokal yang independen. Kegagalan tes harus diselesaikan sebelum tugas terkait dianggap selesai.

## 2. Urutan pekerjaan

| Tahap | Tugas | Hasil pemeriksaan tahap |
|---|---|---|
| A — Fondasi | T01–T10 | Aplikasi dasar, SQL awal, login/policy, audit, UI dan master |
| B — Data dan verifikasi | T11–T18 | Personel, pengajuan, dokumen dan riwayat |
| C — Kasus dan pensiun | T19–T22, T24–T26, T31–T32 | Akses kasus khusus, perkiraan manual, pengingat, cron, mutasi/status dan arsip |
| D — Layar pimpinan | T27–T30 | Peta, angka, filter, pencarian dan CSV konsisten |
| E — Kesiapan paket | T34–T40 | Bukti pengujian, paket cPanel, pemulihan dan serah terima |
| Lanjutan P1 | T23, T33 | Rumus pensiun tervalidasi dan impor sumber lama bila tersedia |

## 3. Fondasi

### [x] T01 — Periksa sumber, repositori dan lingkungan

Prioritas: P0. Dependensi: tidak ada. Referensi: NFR01, NFR10. Penerimaan: AC35.

Hasil: baca `AGENTS.md` yang berlaku, PRD, brainstorming dan struktur proyek; inventaris pekerjaan yang sudah ada; catat PHP/DB/tool yang tersedia, target hosting, dan asumsi A01–A08. Buat `docs/DECISIONS.md` dan format progres.

Verifikasi: tidak ada pekerjaan pengguna yang ditimpa; versi aktual dan hal yang belum dapat diperiksa ditulis. Jangan mengarang akses ke halaman internal situs referensi.

### [x] T02 — Tetapkan model data dan kontrak policy

Prioritas: P0. Dependensi: T01. Referensi: FR01–FR14, NFR02, NFR04. Penerimaan: AC04, AC05, AC06, AC09, AC16.

Hasil: `docs/DATA_DICTIONARY.md`, diagram relasi yang diperlukan, daftar status, normalisasi identitas, relasi dokumen, versi objek, matriks akses dan kontrak query scoped. Pilih strategi enkripsi/pencarian identitas serta pengelolaan kunci.

Verifikasi: ada FK/pemilik untuk semua lampiran; case grant tidak bergantung pada role admin umum; versi dan kunci unik deduplikasi dirancang; kategori baru tidak menjalankan kode konfigurasi.

### [ ] T03 — Bangun kerangka PHP native dan konfigurasi privat

Prioritas: P0. Dependensi: T01. Referensi: NFR01, NFR03, FR02. Penerimaan: AC30, AC31, AC35.

Hasil: bootstrap runtime, router allowlist, URL helper, dependency loader, error handler, request/response, validator, PDO connection, pemisahan lapisan dan konfigurasi contoh. Siapkan mode URL bersih serta fallback yang konsisten.

Verifikasi: halaman dasar dan aset berjalan; kesalahan tidak membocorkan secret; path privat tidak dipublikasikan; rute tidak melakukan include dari masukan pengguna.

### [x] T04 — Buat SQL instalasi, migrasi dan seed terpisah

Prioritas: P0. Dependensi: T02, T03. Referensi: FR01, NFR02. Penerimaan: AC01, AC05, AC06, AC35.

Hasil: `database/binprofkes.sql`, migrasi berversi, tabel/indeks/FK inti, master Militer/PNS, role/permission, dan seed demo terpisah. Sediakan data untuk pengujian minimal dua satuan dan pengguna sintetis dengan cakupan berbeda.

Verifikasi: impor nyata pada mesin yang tersedia; FK/unique bekerja; seed produksi tidak berisi akun universal atau data pribadi; migrasi tidak menghapus data yang ada.

### [x] T05 — Bootstrap administrator dan kewenangan kasus awal

Prioritas: P0. Dependensi: T04. Referensi: FR02, FR09, NFR03, NFR04. Penerimaan: AC02, AC16, AC32.

Hasil: alat CLI satu kali untuk administrator dan penetapan pejabat awal pemberi izin kasus, dengan audit serta mekanisme pemulihan terdokumentasi. Password ditentukan saat bootstrap dan tidak ditulis ke log.

Verifikasi: eksekusi melalui web ditolak; tidak ada bypass query string; bootstrap terkunci setelah berhasil; penetapan izin awal tidak membuka seluruh data kasus kepada admin teknis.

### [ ] T06 — Login, sesi, CSRF dan pembatasan percobaan

Prioritas: P0. Dependensi: T03, T04, T05. Referensi: FR02, NFR03. Penerimaan: AC03, AC35.

Hasil: login/logout, hash/verifikasi password, rotasi sesi, timeout, cookie aman, CSRF seluruh mutasi, rate limit bersama, penonaktifan akun dan pencabutan sesi/izin. Tentukan pemulihan akses sesuai kemampuan instalasi, tanpa pertanyaan keamanan lemah atau password melalui URL.

Verifikasi: login benar/salah, CSRF hilang/salah, sesi kedaluwarsa, akun nonaktif dan percobaan berulang diuji; tidak ada password mentah atau quick login produksi.

### [x] T07 — Policy peran, cakupan dan izin per objek

Prioritas: P0. Dependensi: T02, T06. Referensi: FR02, FR09, NFR04. Penerimaan: AC04, AC08, AC15, AC16, AC26.

Hasil: middleware/policy terpusat dan query scope untuk halaman/API/dokumen/ekspor; peran umum, unit scope, capability kasus dan mekanisme versi izin. Bentuk `docs/ACCESS_MATRIX.md` dari policy yang benar-benar diterapkan.

Verifikasi: akun tanpa scope ditolak; penggantian ID tidak membuka unit lain; role admin/pimpinan tidak otomatis membaca kasus; izin yang dicabut berlaku pada sesi aktif.

### [x] T08 — Audit, riwayat dan utilitas versi

Prioritas: P0. Dependensi: T04, T07. Referensi: FR04, NFR05. Penerimaan: AC09, AC33.

Hasil: audit perubahan/keputusan serta baca/unduh/ekspor sensitif, versi objek, request ID, dan sanitasi log. Riwayat isi sensitif dilindungi terpisah dari log teknis umum.

Verifikasi: event mempunyai pelaku, waktu, objek, hasil; password/token/kronologi mentah tidak masuk log umum; tidak tersedia tombol hapus audit biasa.

### [ ] T09 — Kerangka antarmuka Indonesia dan keadaan halaman

Prioritas: P0. Dependensi: T03, T06, T07. Referensi: NFR06, FR03, FR12. Penerimaan: AC30, AC36.

Hasil: login, sidebar, header, breadcrumb, drawer mobile, komponen form/tabel/filter/pagination, pesan sukses/gagal, empty state, dan fokus keyboard. Aset lokal dan identitas institusi mudah diganti.

Verifikasi: desktop/mobile dan navigasi keyboard berjalan; label jelas; tombol memiliki aksi; form tidak kehilangan masukan saat validasi gagal.

### [ ] T10 — Master kategori, satuan dan referensi personel

Prioritas: P0. Dependensi: T04, T07, T09. Referensi: FR01, FR03. Penerimaan: AC05, AC06.

Hasil: CRUD/nonaktif master yang diperlukan, termasuk kategori tambahan, jenis identitas, hirarki satuan, koordinat, pangkat/golongan, korps/profesi serta master pendidikan/penghargaan.

Verifikasi: kode duplikat dan siklus hierarki ditolak; master terpakai tidak dihapus merusak; koordinat divalidasi; kategori baru tidak menambah formula pensiun secara otomatis.

## 4. Personel, verifikasi dan dokumen

### [x] T11 — Profil personel dan draf perubahan

Prioritas: P0. Dependensi: T08, T09, T10. Referensi: FR03, FR14. Penerimaan: AC06, AC07.

Hasil: profil Militer/PNS, identitas teks unik, status kelengkapan, ID stabil, daftar/filter/pagination, tab profil, dan penempatan awal. Pisahkan draf dari data terverifikasi.

Verifikasi: data belum lengkap dapat menjadi draf; penerbitan awal mengikuti kolom minimal PRD; angka nol identitas terjaga; operator tidak mengubah personel unit lain.

### [x] T12 — Mesin pengajuan dan revisi tetap

Prioritas: P0. Dependensi: T11. Referensi: FR04, NFR02. Penerimaan: AC07, AC08, AC09.

Hasil: draf, kirim, pengembalian, revisi baru, batal; snapshot submitted tetap; versi dasar per objek; kolom payload allowlist. Siapkan handler per modul yang menerapkan perubahan secara terkendali.

Verifikasi: payload tidak dapat mengganti role/owner/status approval; snapshot diajukan tidak bisa diedit; pembatalan bersaing dengan persetujuan ditangani konsisten.

### [x] T13 — Antrean pusat, perbandingan dan persetujuan atomik

Prioritas: P0. Dependensi: T12. Referensi: FR04, NFR02, NFR04. Penerimaan: AC07, AC08, AC09, AC14.

Hasil: daftar pengajuan scoped, diff data/lampiran, catatan pengembalian, row lock/versi dasar, serta transaksi pembaruan, keputusan, dan audit.

Verifikasi: pengajuan sendiri dan scope salah ditolak; dua persetujuan bersamaan tidak menggandakan penerapan; kesalahan di tengah transaksi tidak menerbitkan sebagian perubahan.

### [x] T14 — Penyimpanan unggahan privat dan validasi berkas

Prioritas: P0. Dependensi: T03, T07, T08. Referensi: FR08, NFR03. Penerimaan: AC13, AC31.

Hasil: storage di luar webroot, format PDF/JPG/PNG, batas konfigurasi, validasi MIME/struktur, nama acak, checksum, metadata, serta unduh melalui controller. Cegah traversal, eksekusi berkas dan konsumsi gambar berlebihan.

Verifikasi: ekstensi ganda, MIME tidak sesuai, ukuran berlebih, path buatan, dan akses langsung ditolak; kontrol tidak hanya bergantung pada JavaScript.

### [x] T15 — Hubungkan dokumen dengan objek dan revisi

Prioritas: P0. Dependensi: T12, T13, T14. Referensi: FR08, FR04. Penerimaan: AC13, AC14, AC33.

Hasil: tabel penghubung FK per modul, asosiasi lampiran draf/terbit, versi baru saat penggantian, pemeriksaan pemilik objek/revisi, dan audit unduh.

Verifikasi: lampiran draf hanya terbaca oleh pemohon/pemeriksa berwenang; persetujuan menerbitkan versi tepat; ID berkas lama tidak menjadi jalan melewati policy.

### [x] T16 — Dikum

Prioritas: P0. Dependensi: T13, T15. Referensi: FR05. Penerimaan: AC10, AC14.

Hasil: riwayat, pengajuan, verifikasi, lampiran ijazah, rekap/filter, serta tanggal/tahun dengan tingkat ketelitian. Sumber data tab profil dan rekap sama.

Verifikasi: beberapa pendidikan satu orang tetap satu personel; tahun tidak diubah menjadi tanggal buatan; perubahan belum disetujui tidak masuk rekap terverifikasi.

### [x] T17 — Dikmil

Prioritas: P0. Dependensi: T13, T15. Referensi: FR06. Penerimaan: AC11, AC14.

Hasil: jenis/lembaga/angkatan/periode/status/hasil, keputusan/sertifikat, lampiran, pengajuan dan filter. Kesesuaian kategori memakai konfigurasi terbatas.

Verifikasi: data tidak relevan dibedakan dari belum diisi; periode/status tervalidasi; tidak ada pembatasan kategori yang di-hardcode tanpa keputusan.

### [x] T18 — Penghargaan

Prioritas: P0. Dependensi: T13, T15. Referensi: FR07. Penerimaan: AC12, AC14.

Hasil: banyak penghargaan per personel, pemberi, tingkat, nomor/tanggal keputusan, lampiran, pengajuan dan rekap. Tampilkan peringatan duplikasi potensial dengan dasar yang jelas.

Verifikasi: penghargaan berbeda tetap dapat dicatat; lampiran tidak tersambung ke orang yang salah; perubahan melewati verifikasi.

## 5. Kasus dan perkiraan pensiun

### [x] T19 — Kasus dan relasi beberapa personel

Prioritas: P0. Dependensi: T07, T13, T15. Referensi: FR09. Penerimaan: AC15, AC17, AC33.

Hasil: data kasus, pihak/personel terkait, peran, perkembangan, penanggung jawab, status penanganan yang terpisah dari status verifikasi, dan revisi hasil/keputusan.

Verifikasi: satu kasus tidak digandakan per personel; keputusan mempunyai sumber; kasus yang belum berwenang tidak muncul pada profil, daftar, pencarian atau payload umum.

### [x] T20 — Penugasan dan izin khusus kasus

Prioritas: P0. Dependensi: T05, T07, T19. Referensi: FR02, FR09. Penerimaan: AC15, AC16, AC17.

Hasil: pemberian/pencabutan grant per kasus/tindakan dengan masa berlaku bila diperlukan, penugasan lintas satuan terkontrol, serta pembatasan pencipta kasus. Bootstrap kewenangan awal mengikuti T05.

Verifikasi: pengguna tidak memberi izin sensitif ke diri sendiri; akun admin/pimpinan tanpa grant ditolak; akses kasus tidak membuka profil umum pihak lain; pencabutan langsung berlaku.

### [x] T21 — Verifikasi, lampiran dan ekspor kasus

Prioritas: P0. Dependensi: T15, T19, T20. Referensi: FR08, FR09, FR13. Penerimaan: AC08, AC14, AC15, AC16, AC17, AC33.

Hasil: antrean verifikator kasus, riwayat keputusan, unduh/ekspor dengan capability terpisah, pesan notifikasi umum yang aman, dan jejak baca/unduh/ekspor.

Verifikasi: verifikator umum tidak menerima payload kasus; identitas/isi kasus tidak bocor melalui error/log/CSV/marker; tidak ada penetapan sanksi otomatis.

### [x] T22 — Perkiraan pensiun manual terverifikasi

Prioritas: P0. Dependensi: T11, T13. Referensi: FR10. Penerimaan: AC18, AC19, AC20.

Hasil: tanggal perkiraan, sumber/alasan, metode `manual_verified`, versi dasar, pemeriksa, status perlu tinjau, daftar/filter, serta tampilan sisa waktu. Sediakan keadaan belum ditetapkan/belum dapat dihitung.

Verifikasi: perkiraan belum disetujui tidak dianggap resmi; perubahan dasar memicu tinjau ulang; tanggal lampau tidak otomatis memensiunkan atau mengarsipkan personel.

### [ ] T23 — Rumus pensiun berdasarkan aturan resmi

Prioritas: P1. Dependensi: T22 dan ketersediaan dasar aturan yang terverifikasi. Referensi: FR10. Penerimaan: AC19, AC20, AC21.

Hasil: setelah sumber tersedia, registri aturan, masa berlaku/versi, atribut dan mesin perhitungan terbatas, pengujian batas, serta prosedur aktivasi. Tanpa sumber, fitur tetap tidak aktif; catat dependensi eksternal.

Verifikasi: tidak ada angka usia buatan, `eval`, SQL/config yang dapat dieksekusi, atau penerapan aturan baru diam-diam ke hasil lama. P0 tidak menunggu tugas ini.

### [x] T24 — Mesin tenggat dan deduplikasi pengingat

Prioritas: P0. Dependensi: T04, T22. Referensi: FR11. Penerimaan: AC20, AC21, AC22, AC23.

Hasil: pengurangan bulan kalender, penjepitan akhir bulan, tenggat terkonfigurasi, penerima scoped, unique key, penandaan jadwal digantikan, serta pengingat susulan terbatas sesuai PRD.

Verifikasi: akhir bulan/kabisat, perubahan versi perkiraan, aktivasi terlambat dan tenggat lampau tidak membanjiri pengguna; tidak ada notifikasi dari perkiraan yang perlu tinjau.

### [ ] T25 — Cron CLI, lock, batch dan retry

Prioritas: P0. Dependensi: T24. Referensi: FR11, NFR01, NFR08. Penerimaan: AC22, AC32, AC35.

Hasil: skrip CLI pengingat, lock dengan masa berlaku, transaksi penciptaan notifikasi/penerima, batch, ringkasan hasil dan retry setelah crash. Buat contoh konfigurasi cron dengan path yang dapat diganti.

Verifikasi: penolakan akses web, dua proses bersamaan, lock kedaluwarsa, crash sebelum/sesudah commit, serta pengulangan tanggal sama. Jangan menguji dengan menunggu berjam-jam; gunakan jam uji yang dikendalikan.

### [x] T26 — Notifikasi, status baca dan panel pengingat

Prioritas: P0. Dependensi: T07, T09, T25. Referensi: FR11, FR12. Penerimaan: AC23, AC33, AC36.

Hasil: daftar notifikasi pribadi, badge sesuai izin, status baca, tautan detail, pemberitahuan hasil verifikasi, dan ringkasan kesehatan cron untuk admin operasional.

Verifikasi: pencabutan scope menutup notifikasi lama terkait; judul/konten kasus tidak muncul pada notifikasi umum; status baca satu penerima tidak mengubah penerima lain.

## 6. Peta, laporan dan perubahan kedinasan

### [x] T27 — Layanan agregat dan filter peta

Prioritas: P0. Dependensi: T10, T13, T16, T17, T22, T31, T32. Referensi: FR12, FR13, NFR04. Penerimaan: AC24, AC25, AC26.

Hasil: query scoped pada data terverifikasi, filter lintas riwayat dengan orang unik, populasi/waktu acuan, jumlah tanpa koordinat, serta DTO marker minimal. Cache hanya jika invalidasi izin/data dapat dijamin.

Verifikasi: gabungan beberapa Dikum/Dikmil tidak menggandakan angka; data unit di luar scope dan data kasus tidak masuk payload; hasil sama dengan query pembanding.

### [ ] T28 — Peta interaktif dan tabel alternatif

Prioritas: P0. Dependensi: T09, T27. Referensi: FR12, NFR06. Penerimaan: AC25, AC26, AC30, AC36.

Hasil: Leaflet stabil lokal, marker/cluster jika diperlukan, panel satuan, focus/reset/fit bounds, fullscreen, legenda, filter dan tabel sinkron. Simpan sumber/lisensi basemap dan perilaku saat gagal.

Verifikasi: tanpa koordinat, hasil nol dan basemap gagal tetap dapat dipakai; tidak ada pelacakan GPS/alamat rumah; penggunaan tile mengikuti ketentuan sumber.

### [x] T29 — Kartu ringkasan dan daftar tindak lanjut

Prioritas: P0. Dependensi: T26, T27, T28. Referensi: FR12. Penerimaan: AC23, AC24, AC25, AC26.

Hasil: kartu aktif terverifikasi, kategori, satuan, perkiraan pensiun dalam rentang terlihat, pengajuan sesuai role, serta waktu pembaruan dan definisi populasi.

Verifikasi: kartu/marker/tabel konsisten; jumlah unit tanpa koordinat dijelaskan; ringkasan tidak memunculkan indikator kasus bagi pengguna tanpa izin.

### [x] T30 — Pencarian dan CSV terotorisasi

Prioritas: P0. Dependensi: T16, T17, T18, T21, T22, T27. Referensi: FR13, NFR04. Penerimaan: AC04, AC24, AC27.

Hasil: filter, sort allowlist, pagination server, ekspor CSV dengan kolom dan capability tersendiri, pengendalian sumber daya, serta audit. Ekspor kasus memakai izin khusus.

Verifikasi: filter/cakupan/kolom sama dengan daftar; formula dinetralkan; digit NRP/NIP di dalam berkas terjaga dan panduan impor spreadsheet sebagai teks tersedia; mengubah parameter ekspor tidak melewati policy.

### [x] T31 — Mutasi dan perubahan status bertanggal efektif

Prioritas: P0. Dependensi: T10, T11, T13, T25. Referensi: FR14, FR04. Penerimaan: AC09, AC23, AC28.

Hasil: pengajuan satuan/jabatan/status, persetujuan, tanggal efektif, penerapan segera atau terjadwal, penutupan/pembukaan riwayat, evaluasi ulang scope serta perkiraan yang terdampak. Sediakan tindakan CLI terjadwal untuk perubahan jatuh tempo.

Verifikasi: tanggal masa depan tidak diterapkan lebih awal; dua mutasi bersaing tidak menciptakan penempatan utama ganda; saat efektif, akses operator satuan lama ke data kini dihitung ulang. Izin kasus tetap mengikuti grant eksplisit.

### [x] T32 — Arsip dan pemulihan personel

Prioritas: P0. Dependensi: T13, T31. Referensi: FR14. Penerimaan: AC20, AC29.

Hasil: pengajuan arsip/pemulihan dengan alasan, audit, perlindungan riwayat/dokumen, serta filter aktif/arsip yang konsisten. Perkiraan lewat tidak memicu pengarsipan otomatis.

Verifikasi: relasi tetap utuh setelah arsip/pemulihan; orang yang diarsipkan tidak muncul pada populasi aktif; policy arsip tetap berlaku.

### [ ] T33 — Impor sumber lama setelah pemetaan

Prioritas: P1. Dependensi: T13, T30 dan sumber data yang sah. Referensi: FR03, FR05–FR07, NFR02. Penerimaan: AC04, AC06, AC07.

Hasil: pemetaan kolom, dry-run, validasi duplikasi/cakupan, laporan baris gagal, serta staging ke antrean verifikasi. Tanpa sumber, sediakan catatan kebutuhan dan jangan membuat pemetaan fiktif.

Verifikasi: impor tidak menerbitkan data secara diam-diam, tidak mengubah unit di luar izin, dan tidak mempercayai formula spreadsheet. P0 tidak menunggu tugas ini.

## 7. Pengujian dan serah terima

### [ ] T34 — Pengujian integrasi keamanan dan akses

Prioritas: P0. Dependensi: T21, T26, T30, T31, T32. Referensi: NFR03–NFR05, NFR07. Penerimaan: AC03, AC04, AC08, AC13–AC17, AC23, AC26, AC27, AC33.

Hasil: tes bermakna lintas akun/satuan, perubahan ID, pencabutan izin, self-approval, CSRF, mass assignment, upload, dokumen draf, kasus, notifikasi lama, cache dan ekspor. Jalankan terhadap handler nyata, bukan hanya membandingkan string kode.

Verifikasi: catat perintah/hasil dan perbaiki kegagalan; lingkungan sintetis terpisah dari produksi; tidak melakukan pengujian serangan ke situs referensi tanpa otorisasi.

### [ ] T35 — Pengujian integritas dan aturan bisnis

Prioritas: P0. Dependensi: T13, T21, T25, T27, T31, T32. Referensi: NFR02, NFR07. Penerimaan: AC07, AC09–AC12, AC17–AC25, AC28, AC29.

Hasil: pengujian concurrency/rollback, revisi lampiran, hitung orang unik, tanggal parsial pendidikan, akhir bulan/kabisat, catch-up, perubahan perkiraan, cron bersamaan/crash dan mutasi efektif.

Verifikasi: gunakan kondisi pembanding serta injeksi kegagalan yang terkontrol; periksa database akhir, bukan hanya status HTTP berhasil.

### [ ] T36 — Validasi SQL final dan kompatibilitas runtime

Prioritas: P0. Dependensi: T04, T34, T35. Referensi: NFR01, NFR02, NFR07. Penerimaan: AC01, AC35.

Hasil: sinkronkan SQL instalasi dengan migrasi/schema final, jalankan fresh install dan upgrade berdata, periksa FK/indeks, lint PHP, serta audit dependency. Catat versi yang diuji.

Verifikasi: tidak ada klaim MySQL dan MariaDB sama-sama lulus bila hanya satu diuji. Jika engine target tidak tersedia, catat pemeriksaan yang belum dapat dilakukan dan siapkan perintah reproduksi.

### [ ] T37 — Uji penggunaan dan kinerja

Prioritas: P0. Dependensi: T28, T29, T30, T36. Referensi: NFR06, NFR08. Penerimaan: AC25, AC30, AC36, AC37.

Hasil: pemeriksaan desktop/mobile, keyboard, validasi/empty/error, fallback peta, pagination, pengukuran query/response pada dataset sintetis, serta temuan dan perbaikannya.

Verifikasi: catat dataset, beban, runtime dan hasil p95 aktual; bedakan waktu API dan penyedia peta; jangan mengisi hasil ukur dengan target yang belum diukur.

### [ ] T38 — Paket cPanel, preflight dan perlindungan direktori

Prioritas: P0. Dependensi: T36, T37. Referensi: NFR01, NFR03, NFR10. Penerimaan: AC02, AC30, AC31, AC32, AC35.

Hasil: dua mode pemasangan, contoh path/domain/config, dependency lokal/lockfile/lisensi, preflight kebutuhan PHP/ekstensi/DB/cron/HTTPS, perlindungan direktori, pemeriksaan probe dan `docs/INSTALL_CPANEL.md`.

Verifikasi: cek akses dari seluruh domain/alias yang tersedia menggunakan probe tanpa rahasia; tidak mengunduh konfigurasi asli untuk pengujian. Paket tidak menyertakan secret, data nyata, log atau cadangan produksi. Instalasi lokal selesai tanpa Node pada runtime hosting.

### [ ] T39 — Cadangan dan latihan pemulihan

Prioritas: P0. Dependensi: T36, T38. Referensi: NFR05, NFR09. Penerimaan: AC29, AC34, AC35.

Hasil: `docs/BACKUP_RESTORE.md`, cara memperoleh snapshot konsisten database/dokumen, perlindungan kunci, retensi yang dapat dikonfigurasi, lokasi cadangan privat dan latihan restore ke lingkungan terpisah.

Verifikasi: hitung dan cocokkan relasi/dokumen setelah restore; uji data terenkripsi dapat dibaca dengan kunci tepat; catat waktu dan batas pemulihan aktual. Target retensi/RPO/RTO organisasi tetap perlu ditetapkan.

### [ ] T40 — UAT, dokumentasi dan paket rilis

Prioritas: P0. Dependensi: T34, T35, T36, T37, T38, T39. Referensi: FR01–FR14, NFR10. Penerimaan: AC01–AC38.

Hasil: jalankan alur operator, pusat, pimpinan dan petugas kasus; selesaikan `docs/TEST_REPORT.md`, `docs/OPERATIONS.md`, `docs/DECISIONS.md`, matriks akses, kamus data, catatan batasan, serta paket rilis untuk diperiksa.

Verifikasi: seluruh P0 memiliki bukti; tidak ada tombol mati, data contoh tersamar, atau laporan uji palsu. T23/T33 boleh tetap belum dikerjakan sebagai P1. Deployment ke server aktif hanya dilakukan jika pengguna memberikan instruksi deployment.

## 8. Format catatan progres

Untuk setiap tugas yang dikerjakan, tambahkan catatan dengan format berikut pada log implementasi atau di bawah tugas terkait:

```text
Task: Txx
Status: TODO | IN_PROGRESS | DONE | BLOCKED
Berkas/hasil:
Verifikasi aktual:
Hasil dan keterbatasan:
Keputusan/asumsi yang berubah:
Langkah berikutnya:
```

Jika sesi berlanjut, baca catatan terakhir dan keadaan berkas sebelum mengulang pekerjaan. Jangan menandai implementasi selesai hanya karena dokumen, mockup, atau skeleton sudah dibuat.

## 9. Pemetaan kriteria penerimaan ke tugas utama

| Kriteria | Tugas utama |
|---|---|
| AC01–AC02 | T04, T05, T36, T38 |
| AC03–AC06 | T06, T07, T10, T11, T34 |
| AC07–AC09 | T12, T13, T34, T35 |
| AC10–AC12 | T16, T17, T18, T35 |
| AC13–AC14 | T14, T15, T34 |
| AC15–AC17 | T19, T20, T21, T34 |
| AC18–AC20 | T22, T32, T35; T23 hanya perluasan P1 |
| AC21–AC23 | T24, T25, T26, T34, T35 |
| AC24–AC27 | T27, T28, T29, T30, T34, T35, T37 |
| AC28–AC29 | T31, T32, T35, T39 |
| AC30–AC32 | T03, T25, T28, T38 |
| AC33–AC35 | T08, T34, T36, T38, T39 |
| AC36–AC38 | T09, T37, T40 |

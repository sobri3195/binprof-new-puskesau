# PRD — BINPROFKES PHP Native

Versi: 1.0  
Tanggal: 23 November 2026  
Sumber keputusan: `brainstorming.md`, versi 1.0, 23 November 2026.  
Dokumen terkait: `TASKS.md` dan `PROMPT_CODEX.md`.  
Status: spesifikasi untuk implementasi; aplikasi dan SQL produksi belum dibangun melalui dokumen ini.

## 1. Masalah dan hasil produk

Data identitas, pendidikan, kedinasan, penghargaan, kasus, dan perkiraan pensiun perlu dikelola dalam profil yang konsisten, diperbarui operator satuan, lalu diverifikasi pusat. Pimpinan membutuhkan peta satuan dan personel yang menggunakan data terverifikasi serta memperlihatkan pengingat penting.

Produk menyediakan satu aplikasi PHP native dan database relasional, dapat dipasang pada shared hosting/cPanel melalui subdomain pengguna. Data personel mempunyai identitas internal stabil, riwayat terpisah, dokumen privat, dan jejak verifikasi.

### 1.1 Analisis brainstorming

| Temuan | Penyelesaian dalam PRD |
|---|---|
| Cakupan Militer/PNS dan modul utama sudah dipilih | Menjadi kebutuhan wajib rilis pertama |
| Prosedur perubahan belum mempunyai aturan transaksi rinci | Tetapkan draf, revisi pengajuan, versi dasar, dan persetujuan atomik |
| Peran pusat dapat disalahartikan sebagai akses semua kasus | Pisahkan izin umum, izin kasus, cakupan satuan, dan penugasan kasus |
| Dasar usia pensiun belum tersedia | Mulai dari tanggal perkiraan yang diinput dan diverifikasi; formula otomatis belum aktif |
| Tenggat notifikasi masih berupa usulan | Jadikan konfigurasi; tetapkan deduplikasi, penjadwalan kalender, dan pemulihan kegagalan |
| Tata letak folder bergantung pada penyedia hosting | Sediakan dua pola pemasangan yang menjaga berkas privat di luar seluruh webroot |
| Fitur lama BINPROF belum diinventarisasi seluruhnya | Rilis pertama mengikuti cakupan di dokumen ini; penambahan lainnya dicatat terpisah |

### 1.2 Sasaran dan indikator

| Sasaran | Indikator yang dikumpulkan | Kriteria awal |
|---|---|---|
| Data dapat ditelusuri | Pengajuan dan keputusan memiliki aktor, waktu, versi | Semua perubahan terbit melalui verifikasi |
| Pembatasan akses konsisten | Hasil pengujian lintas satuan, kasus, dokumen, dan ekspor | Seluruh skenario penolakan wajib lulus |
| Peta akurat terhadap database | Selisih jumlah orang pada kartu, marker, tabel, dan ekspor | Tidak ada selisih untuk filter dan populasi sama |
| Pengingat andal | Pengingat jatuh tempo, duplikat, dan hasil cron | Tidak ada duplikat pada pengulangan/retry yang diuji |
| Siap dipasang | Impor SQL, pemeriksaan privat, dan pemulihan cadangan | Bukti hasil pada lingkungan yang disebutkan |

Target waktu verifikasi organisasi dan baseline kualitas data dikumpulkan saat pilot. Dokumen ini tidak mengarang capaian operasional.

## 2. Keputusan pengguna dan asumsi implementasi

### 2.1 Keputusan tetap

- PHP native, database MySQL/MariaDB, dan berkas `binprofkes.sql`.
- Personel Militer dan PNS; kategori dapat ditambahkan.
- Dikum, Dikmil, penghargaan, kasus disiplin/pelanggaran/perkara hukum.
- Pensiun terbatas pada perkiraan tanggal serta pengingat.
- Operator satuan mengisi dan pusat memverifikasi.
- Halaman utama pimpinan berupa peta, angka ringkasan, serta pengingat.
- Pemasangan pada shared hosting/cPanel di subdomain pengguna.

### 2.2 Asumsi yang dipakai agar pembangunan dapat berjalan

| ID | Asumsi awal | Cara mengubah |
|---|---|---|
| A01 | Tanggal perkiraan pensiun diinput dengan sumber/alasan, kemudian diverifikasi | Mesin rumus baru diaktifkan setelah aturan resmi dan atributnya diverifikasi |
| A02 | Pengingat dalam aplikasi; tenggat awal 12, 6, 3, dan 1 bulan | Ubah konfigurasi oleh pengguna berizin; riwayat perubahan dipertahankan |
| A03 | Unggahan PDF/JPG/PNG, maksimal awal 5 MiB per berkas | Sesuaikan batas yang diizinkan hosting; validasi aplikasi tetap berlaku |
| A04 | Cakupan satuan ditetapkan eksplisit | Hierarki tidak otomatis memberi seluruh akses turunan |
| A05 | Zona waktu tampilan awal `Asia/Jakarta`, dapat diubah saat instalasi | Catatan waktu disimpan konsisten dalam UTC; tanggal kalender tidak dikonversi |
| A06 | CSV menjadi ekspor wajib | XLSX/PDF menjadi perluasan dengan pustaka asli bila diminta |
| A07 | Peta memakai Leaflet stabil, aset aplikasi lokal, dan basemap yang dapat dikonfigurasi | Penyedia dan izin penggunaan dipilih saat implementasi |
| A08 | Foto, NIK, alamat rumah, dan data kontak lengkap tidak wajib untuk profil minimal | Hanya dikumpulkan bila kebutuhan dan hak aksesnya ditetapkan |

Asumsi tersebut bukan jawaban tambahan dari pengguna. Catat perubahan asumsi dalam `docs/DECISIONS.md` ketika aplikasi dibangun.

## 3. Cakupan rilis

**P0 — wajib:** akun/peran/cakupan, master data, profil Militer/PNS, riwayat kedinasan, Dikum/Dikmil, penghargaan, kasus dengan izin khusus, dokumen privat, pengajuan/verifikasi, perkiraan pensiun manual terverifikasi, pengingat/cron, dashboard peta, pencarian/CSV, pengarsipan, SQL/migrasi, audit, pengujian, dan paket cPanel.

**P1 — pengembangan berikutnya:** rumus pensiun berbasis aturan tervalidasi, impor massal sumber lama setelah pemetaan, notifikasi email/WhatsApp, ekspor XLSX/PDF, analitik kebutuhan jabatan jika master kebutuhan tersedia.

**Belum termasuk:** rekam medis, kasus klinis, sistem rikkes, chatbot, pelacakan lokasi personel secara langsung, pengambilan database situs referensi, serta administrasi pensiun lengkap. Penyimpanan keputusan kasus tetap termasuk; keputusan pensiun lengkap merupakan cakupan berbeda.

## 4. Pengguna dan model kewenangan

| Peran | Tindakan awal | Batas |
|---|---|---|
| Administrator Sistem | Akun, konfigurasi, master, cakupan sesuai kewenangan | Tidak otomatis memperoleh isi kasus atau dokumen personel |
| Operator Satuan | Draf, pengajuan, perbaikan, lihat hasil | Personel dalam satuan yang ditugaskan |
| Verifikator Pusat | Membaca revisi, menyetujui, mengembalikan | Modul dan satuan yang ditugaskan; tidak menyetujui pengajuan sendiri |
| Pimpinan | Peta, agregat, daftar dan ekspor jika diberi izin | Cakupan satuan yang ditetapkan |
| Petugas Kasus | Draf dan tindak lanjut kasus | Kasus yang ditugaskan secara eksplisit |
| Verifikator Kasus | Verifikasi kasus | Penugasan kasus dan izin verifikasi; berbeda dari pemohon |

Otorisasi menggabungkan akun aktif, tindakan, modul, cakupan objek, dan status data. Penugasan kasus eksplisit merupakan dasar akses isi kasus; cakupan satuan umum saja tidak cukup. Kasus lintas satuan dapat ditangani petugas pusat yang ditugaskan, tanpa membuka semua profil personel di satuan lain.

Izin kasus minimal: `case.create`, `case.read`, `case.write`, `case.submit`, `case.verify`, `case.document.download`, `case.export`, dan `case.grant.manage`. Membuat kasus dibatasi satuan penanggung jawab yang ditugaskan. Tindakan pertama membuat penugasan baca/tulis yang sempit untuk pencipta kasus tersebut, bukan hak membaca seluruh kasus. Penautan personel di luar cakupan memerlukan kewenangan lintas satuan yang ditetapkan.

Pemberian izin sensitif tidak boleh dilakukan kepada diri sendiri. Penugasan awal pejabat pemberi izin kasus dibuat melalui bootstrap CLI terkontrol yang dicatat, bukan tombol bypass. Administrator teknis yang memegang server secara fisik tetap merupakan batas kepercayaan operasional; aplikasi tidak menjanjikan kekebalan terhadap pemilik server.

## 5. Kebutuhan fungsional

| ID | Kebutuhan | Perilaku wajib |
|---|---|---|
| FR01 | Master data | Kategori, jenis identitas, satuan/hierarki, lokasi, pangkat/golongan, korps bila relevan, profesi, pendidikan, penghargaan; tambah kategori tanpa mengubah kode rutin |
| FR02 | Akun dan kewenangan | Login, logout, sesi, reset/rotasi akses terkontrol, akun nonaktif, peran, cakupan, dan izin khusus |
| FR03 | Profil personel | ID tetap; NRP/NIP teks; kategori terpisah dari status dinas; pencarian/filter; status kelengkapan; riwayat kedinasan |
| FR04 | Verifikasi | Draf, pengajuan, pemeriksaan perbedaan, pengembalian, pembatalan, persetujuan atomik, dan riwayat revisi |
| FR05 | Dikum | Banyak riwayat per orang, institusi, jenjang, program studi, tahun/tanggal, status, nomor ijazah dan lampiran |
| FR06 | Dikmil | Jenis, lembaga, angkatan, periode, status, hasil bila relevan, keputusan/sertifikat dan lampiran; relevansi kategori dapat diatur |
| FR07 | Penghargaan | Jenis, pemberi, tingkat, tanggal, nomor keputusan, dokumen dan pemeriksaan duplikasi potensial |
| FR08 | Dokumen | Unggah terotorisasi, asosiasi ke catatan, versi, status draf/terverifikasi, unduh melalui controller dan audit |
| FR09 | Kasus | Disiplin/pelanggaran/perkara hukum; beberapa personel; perkembangan, keputusan, lampiran, dan penugasan khusus |
| FR10 | Perkiraan pensiun | Tanggal manual terverifikasi, sumber/alasan, sisa waktu, indikator dasar belum lengkap, dan riwayat perkiraan |
| FR11 | Pengingat | Tenggat terkonfigurasi, penerima berwenang, notifikasi dalam aplikasi, status baca, cron harian dan retry aman |
| FR12 | Dashboard peta | Marker satuan, ringkasan, filter, panel detail, daftar sinkron, fullscreen, dan fallback tabel |
| FR13 | Pencarian dan laporan | Pencarian, filter, pagination, pengurutan allowlist, ekspor CSV sesuai izin/filter/kolom |
| FR14 | Perubahan kedinasan | Pengajuan mutasi/status/arsip/pemulihan, tanggal efektif, riwayat tetap tersimpan, pembaruan cakupan konsisten |

### 5.1 Validasi data penting

- Profil dapat disimpan sebagai draf dengan data belum lengkap; penerbitan awal minimal memerlukan nama, kategori, jenis dan nomor identitas kedinasan, satuan, serta status dinas.
- Tanggal lahir boleh belum diketahui. Perkiraan yang diinput harus mempunyai tanggal dan sumber/alasan; perhitungan rumus membutuhkan data dasar lengkap.
- Nomor identitas unik per jenis setelah normalisasi yang terdokumentasi. Angka nol depan dipertahankan; identitas bukan primary key.
- Tahun pendidikan yang diketahui tanpa tanggal disimpan sebagai tahun dengan tingkat ketelitian, bukan diberi tanggal 1 Januari buatan.
- Jika tanggal mulai/selesai sama-sama diketahui, selesai tidak lebih awal dari mulai. Status pendidikan membedakan sedang berlangsung dan selesai.
- Master yang sudah dirujuk dinonaktifkan, bukan dihapus secara merusak. Hierarki satuan menolak siklus.
- Penempatan/fasilitas terkait harus konsisten dengan satuan. Satu penempatan utama berlaku pada satu tanggal acuan; riwayat rangkap yang sah perlu jenis penugasan tersendiri.

## 6. Alur perubahan dan verifikasi

### 6.1 Status dan revisi

| Status | Tindakan yang diizinkan | Akibat pada data terbit |
|---|---|---|
| Draf | Pemilik berwenang mengedit, mengirim, membatalkan | Belum mengubah data terverifikasi |
| Diajukan | Pemeriksa membaca, menyetujui atau mengembalikan; pemohon membatalkan bila belum diproses | Snapshot pengajuan tidak dapat diedit |
| Dikembalikan | Pemohon membuat revisi baru dengan catatan perbaikan | Versi lama tetap dapat ditelusuri |
| Disetujui | Keputusan tetap; koreksi memakai pengajuan baru | Data terbit diperbarui secara atomik |
| Dibatalkan | Riwayat dipertahankan | Tidak menerapkan perubahan |

Pengajuan menyimpan modul/objek, pemohon, satuan saat pengajuan, versi dasar, nomor revisi, daftar perubahan, dan referensi lampiran. Payload hanya menerima kolom allowlist; nilai role, pemilik, status persetujuan, dan satuan tidak dipercaya dari form tanpa validasi.

Pada persetujuan, server mengunci pengajuan dan objek terkait, memeriksa akun/izin terkini, pemisahan pemohon-pemeriksa, versi dasar, validasi data, dan kepemilikan dokumen. Perubahan, keputusan, audit, dan kenaikan versi diterapkan dalam satu transaksi. Versi dasar yang sudah berubah menghasilkan konflik yang dapat dipahami pengguna; tidak menimpa diam-diam.

Perubahan yang independen dapat diajukan terpisah. Versi per objek dipakai agar pembaruan satu Dikum tidak selalu membatalkan pengajuan penghargaan lain. Perubahan identitas, satuan, dan atribut pensiun memerlukan pemeriksaan relasi yang terdampak.

### 6.2 Dokumen dalam pengajuan

Dokumen draf hanya dapat dibaca pemohon yang masih berwenang dan verifikator yang ditugaskan. Persetujuan menerbitkan asosiasi dokumen sesuai revisi yang disetujui. Mengganti berkas membuat versi baru; berkas lama tidak ditimpa. Mengetahui ID dokumen tidak memberikan akses.

## 7. Modul kasus

Kasus memiliki nomor/referensi, kategori, satuan penanggung jawab, tanggal laporan, judul singkat, pihak/personel terkait beserta perannya, penanggung jawab, riwayat tahapan, serta hasil/keputusan yang didukung dokumen bila tersedia.

Status penanganan awal: **Laporan**, **Pemeriksaan**, **Keputusan**, dan **Selesai**. Status penanganan berbeda dari status verifikasi data. Tahapan dapat disesuaikan prosedur instansi. Perubahan status beserta hasil penting melewati verifikasi kasus.

Pengguna biasa tidak menerima judul, jumlah, kronologi, badge keberadaan, maupun lampiran kasus melalui profil, peta, autocomplete, log umum, atau ekspor. Notifikasi kasus memakai pesan umum dan memeriksa izin terkini sebelum membuka detail. Aplikasi tidak menetapkan kesalahan, sanksi, atau kelayakan karier secara otomatis.

Petugas berizin baca dapat melihat pihak terkait yang diperlukan dalam kasus yang ditugaskan. Hak tersebut tidak sekaligus membuka seluruh profil pendidikan/kedinasan pihak terkait. Penghapusan relasi pihak atau koreksi keputusan tercatat sebagai revisi.

## 8. Perkiraan pensiun dan pengingat

### 8.1 Sumber tanggal

Rilis P0 memakai `method=manual_verified`: operator mengajukan tanggal perkiraan dan sumber/alasan; pusat memverifikasi. Hasil menampilkan label **Perkiraan**, tanggal, sumber, pemeriksa, serta waktu pembaruan. Bila belum tersedia, tampilkan **Perkiraan belum ditetapkan**.

`method=rule_based` disiapkan sebagai P1. Tanpa aturan tervalidasi, sistem menampilkan **Belum dapat dihitung** dan tidak mengisi usia/tanggal hasil tebakan. Registri aturan dapat menyimpan kategori, atribut, masa berlaku, referensi, versi, dan pemeriksa. Dilarang membuat mesin ekspresi yang menjalankan kode/SQL dari konfigurasi.

Perubahan atribut yang memengaruhi dasar perkiraan menandai perkiraan perlu ditinjau. Perkiraan belum terverifikasi atau perlu ditinjau tidak melahirkan pengingat baru. Lewatnya tanggal tidak mengubah status dinas secara otomatis.

### 8.2 Semantik penjadwalan

Tanggal tenggat diperoleh dengan mengurangi bulan kalender dari tanggal perkiraan dan menjepit hari ke hari terakhir bulan tujuan jika diperlukan. Misalnya, 31 Maret 2027 dikurangi satu bulan menjadi 28 Februari 2027; 31 Maret 2028 menjadi 29 Februari 2028. Waktu job memakai zona waktu aplikasi; tanggal domain tetap `DATE`.

Pada aktivasi awal/jeda job, sistem hanya membuat pengingat susulan untuk tenggat terakhir yang sudah terlewati, bukan membanjiri pengguna dengan seluruh tenggat lama. Tenggat masa depan tetap dijadwalkan. Jika perkiraan sudah lewat, buat satu pesan perlu konfirmasi yang menggantikan tenggat lama yang belum dikirim.

Kunci deduplikasi minimal mengikat personel, versi perkiraan, versi aturan pengingat, jenis tenggat, dan penerima. Kunci unik database menjadi pengaman terakhir. Pergantian versi tidak boleh mengulang pengingat lama secara tidak terkendali; pekerjaan lama ditandai digantikan, dan hanya satu pengingat susulan relevan diterbitkan.

Job mengambil lock dengan masa berlaku, memproses batch terbatas, lalu menyimpan notifikasi dan penerima secara transaksional. Crash sebelum commit dapat diulang; crash sesudah commit tidak menggandakan notifikasi. Lock kedaluwarsa dapat dipulihkan. Hasil menyimpan jumlah berhasil/gagal, waktu, dan alasan teknis yang tidak memuat data sensitif.

Penerima diperiksa ulang saat pembentukan dan saat pembacaan. Pencabutan akses satuan menghilangkan akses ke notifikasi lama yang memuat personel di luar kewenangan. Status baca pribadi terpisah dari status pengiriman.

## 9. Dashboard, peta, dan laporan

Populasi default: personel aktif yang sudah terverifikasi pada tanggal acuan, dibatasi cakupan pengguna. Setiap kartu menyebut populasi dan waktu pembaruan. Angka pensiun mengacu perkiraan terverifikasi yang masih berlaku dan jendela waktu yang terlihat pada filter.

Lokasi adalah koordinat satuan/tempat penugasan. Personel tanpa lokasi satuan tetap dihitung pada tabel dan kartu, dengan jumlah tanpa koordinat dijelaskan agar marker tidak memberi kesan seluruh populasi telah dipetakan. Koordinat tidak diisi secara fiktif.

Filter kategori, satuan, profesi, pangkat/golongan, Dikum, Dikmil dan rentang perkiraan diterapkan konsisten. Gunakan query berbasis orang unik atau `EXISTS` untuk filter riwayat; join beberapa pendidikan tidak menambah jumlah orang. Profesi/subspesialisasi yang saling mencakup tidak dijumlahkan sebagai kategori populasi yang terpisah.

Marker menampilkan nama satuan dan agregat. Identitas personel baru dimuat melalui aksi detail yang diotorisasi. Dataset marker tidak membawa seluruh profil, NRP/NIP, kontak, atau data kasus. Cache, jika dipakai, mengikat izin/cakupan, filter, dan versi data; pencabutan izin tidak mempertahankan akses lewat cache.

CSV mengambil filter dan cakupan yang sama, membatasi kolom, menetralkan formula spreadsheet, dan mempertahankan digit nomor identitas di dalam berkas. Sertakan petunjuk impor kolom identitas sebagai teks, karena aplikasi spreadsheet dapat menafsirkan ulang angka panjang saat membuka CSV otomatis. Jangan memakai formula spreadsheet untuk memaksa format nomor identitas. Cetak/unduh membutuhkan izin tersendiri. Ekspor besar diproses dalam batas hosting dan tidak menghasilkan berkas publik permanen.

## 10. Model data dan integritas

| Kelompok | Entitas inti | Relasi/constraint penting |
|---|---|---|
| Master | `personnel_categories`, `identifier_types`, `ranks`, `corps`, `professions`, `units`, `facilities` | Kode unik, status aktif; hierarki satuan tanpa siklus |
| Personel | `personnel`, `personnel_identifiers` | ID stabil, versi; identitas unik per jenis; status dinas terpisah kategori |
| Kedinasan | `service_assignments` | Penempatan utama tidak tumpang tindih pada tanggal acuan |
| Pendidikan | `dikum_histories`, `dikmil_histories` | FK personel, versi, ketelitian tanggal/tahun, status |
| Penghargaan | `award_types`, `personnel_awards` | FK personel; peringatan duplikasi potensial |
| Kasus | `disciplinary_cases`, `case_personnel`, `case_events`, `case_access_grants` | Kasus-personel banyak ke banyak; grant bertindak spesifik dan dapat kedaluwarsa |
| Dokumen | `documents`, tabel penghubung per modul | Versi, pemilik revisi, checksum, MIME, ukuran, lokasi privat; FK eksplisit |
| Verifikasi | `change_requests`, `change_request_revisions`, `approval_actions` | Revisi pengajuan tetap; versi dasar; satu keputusan final per revisi |
| Perkiraan | `retirement_forecasts`, registri `retirement_rules` bila dipakai | Sumber, metode, versi, status validasi, versi objek dasar |
| Notifikasi | `notification_rules`, `notifications`, `notification_recipients` | Deduplikasi unik; status baca per penerima; referensi objek terotorisasi |
| Akun | `users`, `roles`, `permissions`, `user_roles`, `role_permissions`, `user_unit_scopes` | Akun aktif, versi izin, grant tanpa pemberian sensitif ke diri sendiri |
| Operasional | `audit_logs`, `job_runs`, `job_locks`, `app_settings`, `schema_migrations` | Hasil job, lock, versi skema, audit yang tidak dihapus lewat menu biasa |

Kamus data final harus menyebut tipe, nullability, indeks, FK, aturan normalisasi, perlindungan kolom sensitif, serta retensi. Gunakan InnoDB dan `utf8mb4`, dengan kolasi/fitur JSON yang kompatibel pada mesin target. JSON staging tervalidasi bukan pengganti tabel riwayat terverifikasi.

`binprofkes.sql` ditujukan untuk instalasi kosong, dengan master Militer/PNS dan peran/izin dasar. Jangan mengisi akun universal atau data pribadi produksi. Migrasi pembaruan bernomor/berversi menjaga data yang ada. Data demo berada pada berkas terpisah dan hanya dimuat secara eksplisit ke lingkungan demo.

## 11. Kebutuhan nonfungsional

| ID | Kebutuhan | Ketentuan |
|---|---|---|
| NFR01 | Shared hosting | PHP native; dependency dipaketkan; tidak memerlukan Node/daemon/Redis saat operasi; subdomain dan cron dapat dikonfigurasi |
| NFR02 | Integritas database | Impor SQL nyata, migrasi aman, FK/indeks, transaksi dan pengendalian versi; hanya klaim kompatibilitas yang diuji |
| NFR03 | Keamanan aplikasi | HTTPS, hash password, sesi aman, rate limit, CSRF, escaping, query berparameter, file privat dan secret terpisah |
| NFR04 | Pembatasan akses | Deny by default; policy server pada setiap halaman/API/dokumen/cetak/ekspor; izin sensitif dapat dicabut |
| NFR05 | Audit | Jejak perubahan dan akses kasus/dokumen; log teknis tidak memuat password, token atau isi kasus mentah |
| NFR06 | Penggunaan | Bahasa Indonesia, label/fokus keyboard, mobile, loading/empty/error, tabel alternatif peta, dukungan reduced motion |
| NFR07 | Bukti pengujian | Pengujian integrasi dan negatif untuk aturan kritis; hasil aktual, lingkungan, serta batas verifikasi ditulis |
| NFR08 | Kinerja | Pagination server, batch cron/ekspor, indeks, query terukur; kapasitas hosting ditetapkan dari pengukuran |
| NFR09 | Pemulihan | Cadangan database-dokumen-kunci yang konsisten, retensi, akses terbatas, dan latihan restore |
| NFR10 | Serah terima | Panduan instalasi, operasi, pemeriksaan privasi file, backup/restore, daftar versi dan status tugas |

Sasaran uji kinerja awal, bukan jaminan kapasitas: dataset sintetis 10.000 personel, 100 satuan, serta 50.000 riwayat pendidikan. Ukur halaman daftar dan API agregat pada lingkungan yang dicatat; usulan sasaran p95 masing-masing 2 detik dan 3 detik pada beban uji yang dinyatakan. Waktu tile eksternal dilaporkan terpisah. Sesuaikan sasaran setelah kuota/CPU/memori hosting diketahui.

## 12. Kontrol teknis wajib

Password melalui API hash PHP yang didukung, pemeriksaan `password_verify()`, rehash bila perlu; sesi cookie `Secure`, `HttpOnly`, `SameSite`, rotasi ID login, idle/absolute timeout, serta pencabutan melalui versi izin/status akun. Login memakai rate limit dengan penyimpanan bersama yang tersedia dan pesan kesalahan yang tidak membocorkan keberadaan akun.

Semua perubahan menggunakan token CSRF termasuk AJAX; GET tidak mengubah keadaan. Gunakan allowlist kolom untuk mencegah mass assignment. PDO memakai parameter untuk nilai serta allowlist identifier. Escape keluaran sesuai konteks; kronologi kasus berupa teks biasa. Kebijakan header/CSP disesuaikan asal aset dan penyedia peta yang dipilih.

Unggahan membatasi ukuran, ekstensi dan MIME/struktur sesuai format, nama acak, serta lokasi di luar seluruh webroot. Lampiran tidak dieksekusi dan hanya dikirim melalui controller dengan header sesuai konteks. Batas proses gambar mencegah konsumsi sumber daya berlebihan. Pemindaian antivirus, bila tersedia, dilaporkan sesuai pemeriksaan sebenarnya.

Simpan secret dan material enkripsi di luar webroot/repository. Isi kasus dan identitas sensitif memakai enkripsi kolom melalui pustaka kriptografi terpelihara; kunci mempunyai ID versi dan prosedur pemulihan. Pencarian identitas tepat dapat memakai digest berkunci yang terpisah. Bukan sekadar Base64 atau hash yang tidak dapat dipulihkan untuk isi yang perlu dibaca. Kebutuhan ekstensi kriptografi dicatat pada preflight. Retensi dan pemusnahan mengikuti kebijakan instansi yang perlu ditetapkan, bukan angka hukum yang diasumsikan.

## 13. Arsitektur dan pemasangan

Pemisahan yang disarankan: `Controllers`, `Services`, `Repositories`, `Policies`, `Middleware`, `Views`, konfigurasi privat, database/migrasi, penyimpanan, dependency, dan alat CLI. Router allowlist dan satu helper URL mendukung URL bersih serta fallback `index.php?r=...`.

| Mode | Publik | Privat |
|---|---|---|
| A — document root khusus tersedia | `/home/AKUN/binprof/public` | Folder aplikasi lain berada di luar seluruh document root pada akun |
| B — subdomain dibatasi pada `public_html` | `/home/AKUN/public_html/binprof` hanya front controller/aset | `/home/AKUN/binprof_private` untuk aplikasi, konfigurasi, SQL, storage, dependency, dan CLI |

Path di atas adalah contoh konfigurasi, bukan nilai yang di-hardcode. Pemeriksaan harus mencakup akses melalui domain utama/alias, bukan hanya subdomain. Jangan menyediakan mode yang menaruh SQL/secret publik dengan asumsi nama sulit ditebak. Gunakan probe tanpa rahasia untuk pemeriksaan deny.

Pilih cabang PHP yang masih dipelihara dan tersedia di hosting; target awal kompatibilitas 8.4/8.5, lalu catat versi yang benar-benar diuji. Dependency disiapkan lokal dan disertakan beserta lockfile/lisensi. Hosting tidak diwajibkan memiliki Composer atau Node. Peta memakai versi stabil yang dipilih dan sumber basemap sesuai lisensi; kebijakan tile publik tidak mengizinkan pengunduhan massal untuk paket offline.

Cron berjalan melalui PHP CLI, memakai path absolut dari konfigurasi, menolak eksekusi melalui web, dan mempunyai lock serta batas batch. Jika cron/CLI tidak tersedia, aplikasi menyediakan tindakan pemeriksaan manual berizin dengan CSRF dan menjelaskan keterbatasan jadwal. Bootstrap/install satu kali dikunci setelah selesai.

## 14. Kriteria penerimaan rilis

| ID | Bukti penerimaan yang diperlukan |
|---|---|
| AC01 | SQL instalasi kosong berhasil; migrasi menjaga data lama, tipe/FK/indeks sesuai mesin uji |
| AC02 | Bootstrap membuat admin terkontrol tanpa password universal dan tidak dapat diulang tanpa prosedur pemulihan |
| AC03 | Login/sesi/CSRF/rate limit berjalan; akun nonaktif dan izin dicabut berlaku pada sesi aktif |
| AC04 | Perubahan ID/scope pada halaman, API, dokumen dan ekspor tidak membuka data di luar kewenangan |
| AC05 | Kategori baru dapat ditambah; hierarki satuan menolak siklus; tidak membuat aturan pensiun otomatis |
| AC06 | Militer/PNS memakai identitas teks unik; format nol depan dan status kelengkapan terjaga |
| AC07 | Draf/pengajuan tidak mengganti data terbit; snapshot pengajuan tidak dapat diedit diam-diam |
| AC08 | Pemohon dan pemeriksa tidak sama; pemeriksa tanpa scope/modul ditolak |
| AC09 | Persetujuan bersamaan/stale version ditangani; rollback tidak meninggalkan perubahan setengah selesai |
| AC10 | Beberapa Dikum per orang bekerja; tahun tanpa tanggal tetap tahun; filter tidak menggandakan orang |
| AC11 | Dikmil mendukung banyak riwayat dan relevansi kategori; validasi periode/status sesuai data |
| AC12 | Penghargaan terhubung ke personel/dokumen dan duplikasi potensial dapat ditinjau |
| AC13 | Unggah salah format, ukuran, path, atau kepemilikan ditolak; berkas tidak dapat dieksekusi/diakses langsung |
| AC14 | Lampiran draf dan lampiran terverifikasi mengikuti izin serta revisi yang benar |
| AC15 | Pengguna biasa/admin teknis tidak memperoleh isi atau indikator keberadaan kasus tanpa grant |
| AC16 | Grant kasus membatasi tindakan dan dapat dicabut; pemberian izin sensitif kepada diri sendiri ditolak |
| AC17 | Satu kasus dapat mempunyai beberapa personel; akses kasus tidak membuka profil lain secara umum; keputusan memiliki sumber/riwayat |
| AC18 | Perkiraan manual mempunyai sumber dan verifikator; tanggal tidak muncul terverifikasi sebelum disetujui |
| AC19 | Tanpa aturan atau data dasar, formula tidak menghasilkan tanggal tebakan; perubahan dasar menandai perlu tinjau |
| AC20 | Lewatnya perkiraan tidak mengubah status dinas resmi menjadi pensiun |
| AC21 | Pengurangan bulan benar untuk akhir bulan, tahun kabisat dan zona waktu operasi |
| AC22 | Cron ulang/bersamaan/crash-retry tidak menggandakan atau menghilangkan notifikasi ter-commit |
| AC23 | Pencabutan scope menutup notifikasi lama terkait; koreksi perkiraan menggantikan jadwal lama secara terkontrol |
| AC24 | Marker, kartu, tabel dan CSV menghitung orang unik pada filter/populasi/cakupan yang sama |
| AC25 | Satuan tanpa koordinat tetap tersedia di tabel; kegagalan basemap tidak mematikan pencarian/data |
| AC26 | Respons peta/cache tidak membawa identitas tersembunyi, kontak, atau data kasus |
| AC27 | Ekspor sesuai izin/kolom/filter, menetralkan formula, mempertahankan digit identitas dalam berkas, serta menyertakan panduan impor teks |
| AC28 | Mutasi efektif memperbarui riwayat/cakupan atomik; jadwal masa depan tidak berpindah lebih awal |
| AC29 | Arsip/pemulihan melalui verifikasi; riwayat dan dokumen tetap konsisten |
| AC30 | Semua URL/form/aset bekerja pada mode routing bersih dan fallback yang didukung |
| AC31 | SQL, config, log, sesi, upload dan cadangan tidak dapat diakses melalui semua domain/alias yang diuji |
| AC32 | Cron CLI menolak web, menggunakan lock dan mencatat hasil; fallback manual tetap berizin |
| AC33 | Akses sensitif dan perubahan tercatat tanpa password/token/isi kasus mentah dalam log umum |
| AC34 | Restore database, dokumen dan kunci yang diperlukan berhasil pada lingkungan terpisah |
| AC35 | Versi PHP/DB/dependency yang diuji dan keterbatasan lingkungan dicatat apa adanya |
| AC36 | Form/label/fokus, mobile, keadaan kosong/gagal, dan tabel alternatif dapat digunakan |
| AC37 | Pengukuran kinerja menyebut dataset, beban, lingkungan, hasil aktual serta tindak lanjut bila meleset |
| AC38 | Semua P0 diuji end-to-end; tidak ada tombol mati/data demo tersamar sebagai produksi; panduan sesuai paket |

## 15. Paket hasil implementasi dan definisi selesai

Paket implementasi mencakup aplikasi, aset/dependency lokal, `binprofkes.sql`, migrasi, seed demo terpisah bila disediakan, konfigurasi contoh tanpa secret, skrip bootstrap/cron, serta panduan `INSTALL_CPANEL.md`, `OPERATIONS.md`, `BACKUP_RESTORE.md`, `ACCESS_MATRIX.md`, `DATA_DICTIONARY.md`, `DECISIONS.md`, dan `TEST_REPORT.md`.

P0 selesai setelah kriteria penerimaan lulus pada lingkungan yang disebutkan, hasil pengujian tersimpan, dan paket dapat diperiksa. Jika lingkungan hosting belum dapat diakses, nyatakan pemeriksaan hosting belum dilakukan; keberhasilan lokal tidak boleh disebut deployment produksi. Penyiapan paket dan validasi lokal berada dalam lingkup implementasi; mengubah server aktif memerlukan instruksi deployment yang sesuai.

## 16. Ketergantungan dan rincian terbuka

Subdomain, akun/kemampuan cPanel, aset resmi, master satuan/koordinat, identitas pejabat pemberi izin kasus, serta kebijakan retensi belum diberikan. Gunakan konfigurasi dan data sintetis untuk pembangunan; jangan mengarang data organisasi sebagai data nyata. Dasar peraturan pensiun perlu diverifikasi sebelum fitur rumus otomatis diaktifkan.

Riwayat referensi situs hanya menjadi acuan produk. Halaman internal, database, dan fungsi backend situs tersebut belum diverifikasi dalam penyusunan PRD. Jangan menyalin akun/kredensial atau mengklaim integrasi yang belum dibangun.

## 17. Referensi teknis

- [PHP — Supported Versions](https://www.php.net/supported-versions.php)
- [PHP — password_hash](https://www.php.net/manual/en/function.password-hash.php)
- [PHP — PDO::prepare](https://www.php.net/manual/en/pdo.prepare.php)
- [OWASP — Authorization](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)
- [OWASP — File Upload](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [OWASP — CSRF Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP — Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [cPanel — Cron Jobs](https://docs.cpanel.net/cpanel/advanced/cron-jobs/)
- [cPanel — Create a New Domain](https://docs.cpanel.net/cpanel/domains/domains/create-a-new-domain/)
- [Leaflet](https://leafletjs.com/)
- [OpenStreetMap Foundation — Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/)

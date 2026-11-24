# Progres implementasi

| Tugas | Status | Bukti dan batas |
|---|---|---|
| T01–T05, T07–T08 | Selesai lokal | Checkout kosong, SQL MariaDB, bootstrap terkunci, scope satuan/modul, audit dan versi dasar. |
| T06, T09 | Berjalan lokal | Login, rate limit, CSRF, versi sesi, label/fokus, dan navigasi. TLS dan UAT hosting masih tertunda. |
| T10 | Selesai lokal | Kategori tambahan tersedia saat input, master nonaktif tetap tampil pada riwayat, duplikat dan hierarki bersiklus ditolak, serta pasangan koordinat tervalidasi. |
| T11–T13 | Selesai lokal | Profil, draf, pengembalian, snapshot, self-approval, stale version dan konkurensi diuji. Simpan dan ajukan bersifat atomik. Navigasi riwayat mengikuti izin per revisi. |
| T14–T18 | Selesai lokal | Validasi berkas, izin draf/terbit serta pencabutan akses lampiran historis, pendidikan bertahun parsial, Dikmil, dan peringatan duplikasi penghargaan diuji. Rekap pendidikan dan penghargaan menampilkan catatan terverifikasi dengan filter yang sama pada halaman dan CSV. |
| T19–T22 | Selesai lokal | Kasus beberapa pihak, grant per tindakan, ekspor, pencabutan, dan perkiraan manual terverifikasi diuji. |
| T24–T26 | Selesai lokal | Akhir bulan, catch-up, deduplikasi, rollback/retry, perubahan scope, dan notifikasi. |
| T27–T30 | Selesai lokal | Agregat personel unik, fokus dan reset peta, fallback tabel dan marker saat latar lokal gagal, serta CSV scoped. Provider tile eksternal opsional menunggu konfigurasi instansi. |
| T31–T32 | Selesai lokal | Mutasi masa depan, perubahan scope saat efektif, riwayat, arsip dan pemulihan. |
| T34–T37 | Berjalan lokal | Integrasi HTTP, uji transaksi, lint, browser, impor/migrasi dan benchmark. Sebagian skenario penerimaan serta runtime hosting masih perlu uji. |
| T38 | Terverifikasi lokal, menunggu host target | ZIP dari commit Git, preflight kedua layout, penolakan root yang tumpang tindih dan symlink sesi/dokumen publik, serta probe HTTP sintetis lulus. Apache/cPanel, alias nyata, TLS, kuota dan cron pada host target masih perlu diperiksa. |
| T39–T40 | Menunggu verifikasi deployment/UAT | Restore terpisah tersedia. Retensi, target pemulihan dan UAT instansi belum tersedia. |
| T23, T33 | P1 | Menunggu dasar aturan resmi pensiun dan sumber data lama yang sah. |

Rincian perintah dan hasil ada di [TEST_REPORT.md](TEST_REPORT.md). Gunakan basis data sintetis kosong saat mengulang tes HTTP. Prioritas berikutnya adalah memverifikasi pemasangan pada host uji serta menjalankan skenario penerimaan yang belum tercakup sebelum pemakaian produksi.

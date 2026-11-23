# Progres implementasi

| Tugas | Status | Bukti dan batas |
|---|---|---|
| T01–T05, T07–T08 | Selesai lokal | Checkout kosong, SQL MariaDB, bootstrap terkunci, scope satuan/modul, audit dan versi dasar. |
| T06, T09–T10 | Berjalan lokal | Login, rate limit, CSRF, versi sesi, label/fokus, dan navigasi. TLS hosting dan sebagian skenario master UAT menunggu pengujian. |
| T11–T13 | Selesai lokal | Profil, draf, pengembalian, snapshot, self-approval, stale version dan konkurensi diuji. |
| T14–T18 | Selesai lokal | Validasi berkas dan izin draf/terbit, pendidikan bertahun parsial, Dikmil, dan peringatan duplikasi penghargaan diuji. |
| T19–T22 | Selesai lokal | Kasus beberapa pihak, grant per tindakan, ekspor, pencabutan, dan perkiraan manual terverifikasi diuji. |
| T24–T26 | Selesai lokal | Akhir bulan, catch-up, deduplikasi, rollback/retry, perubahan scope, dan notifikasi. |
| T27–T30 | Berjalan lokal | Agregat unik, fallback tanpa koordinat, CSV scoped dan browser. Uji provider tile opsional menunggu konfigurasi instansi. |
| T31–T32 | Selesai lokal | Mutasi masa depan, perubahan scope saat efektif, riwayat, arsip dan pemulihan. |
| T34–T37 | Berjalan lokal | Integrasi HTTP, uji transaksi, lint, browser, impor/migrasi dan benchmark. Sebagian skenario penerimaan serta runtime hosting masih perlu uji. |
| T38–T40 | Menunggu verifikasi deployment/UAT | Paket, panduan dua mode dan restore terpisah tersedia. Uji domain, konfigurasi TLS, kuota, cron hosting dan UAT instansi belum tersedia. |
| T23, T33 | P1 | Menunggu dasar aturan resmi pensiun dan sumber data lama yang sah. |

Rincian perintah dan hasil ada di [TEST_REPORT.md](TEST_REPORT.md). Gunakan basis data sintetis kosong saat mengulang tes HTTP. Prioritas berikutnya adalah memverifikasi pemasangan pada host uji serta menjalankan skenario penerimaan yang belum tercakup sebelum pemakaian produksi.

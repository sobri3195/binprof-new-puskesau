# Cadangan dan pemulihan

1. Jadwalkan jendela pemeliharaan. Hentikan mutasi web dan cron sementara untuk mendapatkan titik yang konsisten antara database dan dokumen. Catat waktu UTC, versi aplikasi, versi skema, jumlah objek dan jumlah berkas.
2. Buat dump transaksi database dan salin direktori dokumen privat dari titik yang sama. Catat checksum arsip. Simpan cadangan terenkripsi di luar document root, dengan akses terbatas dan retensi sesuai kebijakan instansi.
3. Simpan keyring secara terpisah dari arsip basis data dan dokumen. Sertakan kunci aktif, kunci versi lama, dan kunci digest. Siapkan pemulihan kunci dengan pengelola yang berwenang.
4. Buat database kosong di lingkungan terpisah. Pulihkan dump, dokumen dan keyring. Atur DSN privat, buat ulang sesi kosong, dan arahkan konfigurasi ke lokasi hasil restore. Jangan jalankan bootstrap terhadap dump yang sudah berisi akun.
5. Bandingkan jumlah personel dan relasi, periksa lampiran berdasarkan checksum, buka identitas terenkripsi, dan uji login dengan akun sintetis atau uji sesuai prosedur institusi. Periksa pekerjaan terjadwal sebelum menyalakan cron kembali.

Kunci aktif dapat dinaikkan dengan menambahkan kunci baru dan mengganti ID aktif secara atomik di keyring. Pertahankan kunci historis selama envelope lama atau cadangannya masih dibutuhkan. Rotasi kunci digest memerlukan migrasi indeks yang terkendali karena digest digunakan untuk pencarian dan keunikan.

Satu latihan restore sintetis lokal berhasil sebagaimana dicatat pada [laporan uji](TEST_REPORT.md). Target RPO, RTO, retensi, dan latihan di hosting ditetapkan pemilik layanan.

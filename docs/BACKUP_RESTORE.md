# Cadangan dan pemulihan

## Ambil cadangan

1. Jadwalkan jendela pemeliharaan. Jeda permintaan web dan cron agar audit, penjadwalan, database, dan dokumen memakai titik yang sama.
2. Catat waktu UTC, versi aplikasi, dan versi skema. Dari konfigurasi sumber, tulis manifest ke lokasi privat yang baru. Jalankan perintah dengan hak akses file yang terbatas.

   ```sh
   umask 077
   php cli/verify-restore.php > /path/privat/manifest.json
   ```

3. Setelah perintah berhasil, buat dump transaksi database dengan opsi `--single-transaction --quick --hex-blob` pada klien dump MySQL/MariaDB. Salin direktori dokumen privat dari jendela yang sama. Gunakan `--hex-blob` agar kolom biner ikut dipulihkan dengan tepat.
4. Simpan manifest bersama dump dan dokumen. Catat checksum arsip. Enkripsi cadangan dan simpan di luar document root dengan akses terbatas. Simpan keyring terpisah dengan kunci aktif, kunci historis, dan kunci digest.
5. Tetapkan masa simpan atau jumlah generasi cadangan di media penyimpanan sesuai kebijakan instansi. Tinjau retensi manifest, dump, dokumen dan keyring bersama sehingga versi kunci tersedia selama masih ada arsip yang membutuhkannya.

## Pulihkan di lingkungan terpisah

1. Buat database kosong dan pulihkan dump. Salin dokumen dan keyring ke lokasi privat baru. Atur DSN dan lokasi berkas pada konfigurasi pemulihan. Siapkan direktori sesi kosong.
2. Sebelum menerima permintaan web atau menjalankan cron, bandingkan hasil dengan manifest sumber melalui konfigurasi pemulihan.

   ```sh
   umask 077
   BINPROF_CONFIG=/path/privat/restore.php php cli/verify-restore.php \
       --expect /path/privat/manifest.json > /path/privat/hasil-verifikasi.json
   ```

3. Kode keluar 0 berarti isi tabel terkelola cocok dengan manifest. Pemeriksaan juga memvalidasi relasi inti, kemampuan dekripsi, digest nomor kedinasan dan SHA-256 lampiran. Kode keluar 1 menandakan verifier gagal atau menemukan perbedaan. Tinjau pesan per tabel pada stderr dan pulihkan komponen yang terkait sebelum membuka akses.
4. Setelah verifikasi lulus, uji login dan dokumen sesuai prosedur instansi. Periksa pekerjaan terjadwal sebelum menyalakan cron. Catat waktu pemulihan dan hasil uji.

Verifier membaca database dalam transaksi read-only. Laporan JSON berisi versi skema, jumlah baris, dan hash tiap tabel. Gunakan paket verifier dari versi skema yang sama. Jadwalkan pemeriksaan saat penyimpanan dokumen stabil agar hash database dan berkas merujuk ke keadaan yang sama.

Saat rotasi, tambahkan kunci baru dan ganti ID aktif secara atomik di keyring. Pertahankan versi historis selama ada envelope atau arsip yang menggunakannya. Rotasi kunci digest memerlukan migrasi indeks karena digest digunakan untuk pencarian dan keunikan.

Hasil latihan lokal tercatat di [laporan uji](TEST_REPORT.md). Target RPO, RTO, retensi, dan pelaksanaan di hosting ditetapkan pemilik layanan.

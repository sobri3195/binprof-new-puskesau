# Operasional BINPROFKES

1. Administrator menambahkan master dan akun. Tentukan cakupan satuan eksplisit dan modul setiap verifikator.
2. Operator membuka Personel, membuat draf, dan mengunggah dokumen. Tombol **Simpan dan ajukan** memakai nilai formulir saat ini dalam satu transaksi. Jika validasi gagal, perbaiki masukan yang ditampilkan kembali. Gunakan revisi baru setelah pengembalian. Untuk versi dasar usang, perbarui versi dasar, periksa perubahan, dan unggah lampiran yang diperlukan pada revisi baru.
3. Verifikator membuka Pengajuan, membandingkan versi dasar dan usulan, memeriksa lampiran, lalu menyetujui atau mengembalikan dengan catatan. Tautan pengajuan yang sudah diketahui membuka revisi terakhir yang pernah diajukan saat operator sedang memperbaiki draf. Navigasi riwayat mengikuti izin yang masih berlaku. Lampiran kasus membutuhkan grant unduh tersendiri.
4. Pimpinan memeriksa ringkasan, tabel unit tanpa koordinat, serta daftar personel yang mengikuti filter. Perkiraan terlewati menghasilkan permintaan konfirmasi. Status dinas mengikuti pengajuan kedinasan resmi.
5. Pejabat penugasan memberi grant per tindakan berdasarkan ID kasus dan akun. Petugas dapat mengganti kata sandi awal sebelum menerima grant sensitif. Akses baca kasus tidak mengalihkan orang ke profil umum pihak terkait.

## Cron dan pemulihan akun

Jadwalkan dari cPanel menggunakan path PHP CLI yang benar. Gunakan zona waktu instalasi untuk menentukan waktu jadwal yang sesuai.

```cron
15 * * * * /absolute/path/to/php /home/AKUN/binprof_private/cli/cron.php >> /home/AKUN/private/cron.log 2>&1
```

Eksekusi berkala mendukung mutasi efektif dan kelanjutan batch pengingat. Lease membatasi proses yang bertumpang tindih. Status `partial` akan dilanjutkan oleh panggilan berikutnya. Status `conflicts` perlu ditinjau dari daftar mutasi terjadwal lalu diajukan kembali dengan versi dasar terbaru. Tombol pemeriksaan manual memerlukan izin operasional dan hanya bekerja sewaktu dipanggil.

Akun dapat mengganti kata sandi dengan membuktikan kata sandi lama. Pemulihan CLI oleh pemilik sistem memakai STDIN privat dengan objek `id`, `action` bernilai `reset`, dan `password`. Perintah yang sama mengatur kapabilitas kasus lewat `action` bernilai `case-capabilities` dan boolean `create` serta `cross_unit`. Audit merekam operasi. Hapus arsip input sementara setelah digunakan.

## CSV

Gunakan izin Ekspor personel atau grant ekspor pada kasus. Personel memakai filter dan urutan daftar, maksimum 5.000 baris per permintaan. Dataset lebih besar perlu dibagi per satuan atau filter lain.

Menu **Pendidikan** dan **Penghargaan** menampilkan satu baris per riwayat terverifikasi dalam cakupan satuan. Filter riwayat diterapkan pada baris, lalu diteruskan ke CSV. Rekap pendidikan mempertahankan tahun saja bila tanggal lengkap tidak diketahui. Daftar personel dan peta tetap menghitung satu orang per profil.

Di spreadsheet, impor lewat menu **Data → Dari teks/CSV**, pilih UTF-8 dan pemisah koma, lalu ubah kolom **Nomor kedinasan** menjadi tipe **Teks** sebelum memuat. Pembukaan otomatis dapat menghapus nol awal atau membulatkan nomor panjang. Nilai yang dapat dibaca sebagai formula dinetralkan dengan awalan apostrof.

Aplikasi memvalidasi jenis, ukuran, dan struktur dasar lampiran serta mengenkripsi penyimpanan. Antivirus belum terpasang. Penetapan retensi, jadwal cadangan, dan pemeriksaan audit mengikuti kebijakan instalasi.

# Operasional BINPROFKES

1. Administrator menambahkan master dan akun. Tentukan cakupan satuan eksplisit dan modul setiap verifikator.
2. Operator membuka Personel, membuat draf, dan mengunggah dokumen. Tombol **Simpan dan ajukan** memakai nilai formulir saat ini dalam satu transaksi. Jika validasi gagal, perbaiki masukan yang ditampilkan kembali. Gunakan revisi baru setelah pengembalian. Untuk versi dasar usang, perbarui versi dasar. Perubahan pada kolom berbeda digabung. Bila kolom yang sama berubah di data terbit dan usulan, pilih nilai masing-masing sebelum melanjutkan. Jika draf berubah di tab lain, muat ulang dan periksa nilai tersimpan. Unggah lampiran yang diperlukan pada revisi baru.
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

## Ekspor laporan

Gunakan izin Ekspor personel atau grant baca dan ekspor pada kasus. Ekspor memakai filter dan urutan daftar. Batas per permintaan adalah 5.000 baris untuk CSV dan XLSX serta 250 baris untuk PDF. Dataset lebih besar perlu dibagi per satuan atau filter lain.

Menu **Pendidikan** dan **Penghargaan** menampilkan satu baris per riwayat terverifikasi dalam cakupan satuan. Filter riwayat diterapkan pada halaman dan ekspor. Rekap pendidikan mempertahankan tahun saja bila tanggal lengkap tidak diketahui. Daftar personel dan peta tetap menghitung satu orang per profil.

Format XLSX menyimpan setiap sel sebagai teks agar nol awal pada nomor kedinasan dan nilai yang menyerupai formula tetap utuh. Untuk CSV di spreadsheet, gunakan menu **Data → Dari teks/CSV**, pilih UTF-8 dan pemisah koma, lalu ubah kolom **Nomor kedinasan** menjadi tipe **Teks** sebelum memuat. Pembukaan otomatis dapat menghapus nol awal atau membulatkan nomor panjang. CSV menambahkan awalan apostrof pada nilai yang dapat dibaca sebagai formula.

## Impor data lama

1. Gunakan sumber sah yang telah dipetakan ke satuan, kategori, jenis identitas, dan master instansi. Pisahkan satu berkas untuk setiap pasangan modul dan satuan. Profil membuat personel baru. Dikum, Dikmil, dan penghargaan memakai ID personel terverifikasi dari satuan terpilih.
2. Pada **Impor data lama**, pilih modul dan satuan. Unggah CSV UTF-8 berpemisah koma atau titik koma, maksimal 100 baris data dan 128 KiB. Masukkan judul kolom CSV sesuai sumber. Bagian **ID master dan kode nilai** menampilkan nilai yang dapat digunakan. Gunakan tanggal `YYYY-MM-DD`.
3. Periksa laporan duplikasi, nilai tidak valid, dan cakupan pada pratinjau. Perbaiki semua baris yang ditandai. Pratinjau memeriksa data yang boleh dibaca operator, drafnya, dan baris pada berkas. Pemeriksaan unik nomor kedinasan lintas satuan berlaku saat verifikator menerbitkan profil. Pratinjau valid berlaku 10 menit dan terikat ke sesi operator.
4. Buat draf dan periksa antrean **Pengajuan**. Perubahan yang membuat salah satu baris tidak valid menggagalkan seluruh batch. Ajukan draf yang sudah diperiksa melalui alur verifikasi yang berlaku.

## Kebutuhan jabatan

Administrator mencatat jumlah kebutuhan untuk setiap satuan, bulan, dan nama jabatan yang disahkan. Gunakan nama yang sama dengan riwayat kedinasan. Simpan kembali kombinasi yang sama untuk memperbarui jumlah. Pengguna dengan akses baca melihat selisih dalam cakupan satuan masing-masing. Jumlah terisi menghitung personel aktif pada akhir bulan lampau atau hari ini untuk bulan berjalan. Bulan mendatang hanya menampilkan target.

## Lampiran draf

Pada revisi draf, operator dapat melepas lampiran atau memilih lampiran yang akan diganti saat mengunggah. Penggantian menyimpan berkas baru dan mempertahankan berkas lama secara privat untuk audit dan pemulihan. Setelah diajukan, perubahan lampiran memakai revisi baru.

Aplikasi memvalidasi jenis, ukuran, dan struktur dasar lampiran serta mengenkripsi penyimpanan. Antivirus belum terpasang. Penetapan retensi, jadwal cadangan, dan pemeriksaan audit mengikuti kebijakan instalasi.

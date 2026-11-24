# Keputusan implementasi

PRD menjadi kontrak perilaku. Brainstorming memberi arah produk dan rancangan menu. TASKS menentukan prioritas dan bukti penerimaan. PROMPT_CODEX merinci isi paket dan cara kerja. Empat dokumen sumber dipertahankan di repositori. Tanda selesai di TASKS mengikuti bukti uji, sementara cakupan rilis tetap ditentukan PRD.

| Asumsi | Implementasi |
|---|---|
| A01 | Perkiraan tanggal manual terverifikasi dengan sumber. T23 membutuhkan aturan resmi tervalidasi. |
| A02 | Notifikasi dalam aplikasi. Ambang awal 12, 6, 3, 1 bulan. Perubahan konfigurasi menaikkan versi aturan. |
| A03 | PDF, JPEG, PNG. Batas awal 5 MiB, dimensi gambar maksimal 24 megapiksel, sepuluh berkas per revisi. Pemeriksaan struktur dasar dan MIME dijalankan. |
| A04 | Cakupan satuan eksplisit tanpa pewarisan. Verifikator umum membutuhkan penugasan modul. Grant kasus terpisah. |
| A05 | Zona kalender default Asia/Jakarta. Waktu aktivitas UTC. |
| A06 | CSV, XLSX dan PDF memakai filter dan izin yang sama. XLSX menyimpan nomor kedinasan sebagai teks. Pustaka PDF dan font disertakan dalam paket privat. |
| A07 | Leaflet 1.9.4 lokal. Peta dasar regional Natural Earth hasil ekstraksi dari world-atlas 2.0.2. Penyedia tile HTTPS opsional melalui konfigurasi. |
| A08 | Profil mengumpulkan nama, atribut kedinasan, serta tanggal lahir opsional. Foto, alamat rumah, NIK dan kontak tidak dikumpulkan. |

- Repositori lokal dan remote kosong pada awal implementasi. Struktur dan desain antarmuka dibangun dari spesifikasi. Halaman internal situs rujukan tidak diperiksa.
- Peran umum menentukan tindakan. Cakupan satuan dan penugasan modul menentukan objek. Kasus memakai grant per tindakan dengan kedaluwarsa opsional.
- Satu tabel riwayat pendidikan relasional memakai discriminator Dikum atau Dikmil. Jenis referensi juga diperiksa melalui allowlist di server.
- Dokumen mengikat FK gabungan ke revisi, lalu revisi mengikat FK pengajuan ke objek yang relevan. Revisi lama dengan dokumen tetap tersimpan. Revisi baru memerlukan unggahan baru jika lampiran perlu diterbitkan lagi. Lampiran pada draf bisa dilepas atau diganti sebelum diajukan. Migrasi 003 menyimpan waktu pelepasan tanpa membuang berkas privat.
- Waktu efektif adalah tanggal kalender. Riwayat interval memakai ujung kanan eksklusif. Dua perubahan yang terjadi pada tanggal yang sama tetap meninggalkan riwayat, dengan interval hari kosong untuk keadaan antara keduanya.
- Perubahan profil atau kedinasan menandai perkiraan untuk ditinjau secara konservatif. Satu pengajuan mutasi terjadwal dapat menjadi konflik jika versi dasar berubah sebelum tanggal efektif.
- Data produksi dimulai dari master Militer/PNS tanpa akun universal. Demo sintetis diberi label DATA DEMO. Koordinat nyata, aset instansi, retensi, serta target pemulihan instalasi menunggu keputusan pemilik layanan.
- T33 memiliki alur impor CSV generik dengan pemetaan oleh operator. Uji penerimaan memerlukan sumber data sah, pencocokan ID/master dan konfirmasi aturan duplikasi dari instansi.
- T23 tetap bergantung pada dokumen aturan resmi yang tervalidasi, kategori dan atribut yang tercakup, tanggal berlaku, serta penanggung jawab pemeriksaan. Notifikasi email dan WhatsApp menunggu penetapan penyedia, penerima yang disetujui, templat pesan, dan pengaturan operasional instansi.

- Keberlakuan Dikmil diturunkan dari master kategori. Riwayat historis tetap terbaca. Kategori yang tidak mengikuti Dikmil menampilkan status tidak berlaku pada profil.
- Dasar perkiraan P0 memakai kategori, tanggal lahir, pangkat, golongan, korps, serta status dinas. Koreksi ejaan, jabatan dan lokasi penugasan menjaga validitas perkiraan. Perubahan cakupan penerima tetap dievaluasi oleh akses dan cron. Revisi lama dengan basis versi profil numerik tetap diperiksa terhadap versi profil saat ini dan dapat diperbarui melalui rebase.

# Matriks akses

| Tindakan | Syarat server |
|---|---|
| Kelola master, akun dan jadwal | Administrator sistem. Edit akun memperbarui versi sesi. Perubahan akses sendiri memerlukan administrator lain. |
| Daftar, pencarian, detail, agregat | Operator, Verifikator pusat atau Pimpinan, dengan cakupan satuan objek. |
| Buat, ubah, ajukan, batalkan | Operator dengan cakupan yang berlaku. Draf dan keputusan terkait terikat pemilik. |
| Baca dan putuskan revisi terajukan | Verifikator dengan satuan asal, satuan objek saat ini dan modul eksplisit. Riwayat baca mencakup revisi yang pernah diajukan. Keputusan hanya berlaku pada revisi terkini yang diajukan. Mutasi juga memerlukan cakupan tujuan. Pemohon dilarang memutuskan sendiri. |
| Unduh lampiran umum terverifikasi | Akses baca profil, cakupan satuan saat ini, dan peran Unduh personel. |
| Unduh draf atau revisi umum terajukan | Pemohon dalam cakupan atau verifikator modul berwenang setelah pengajuan dikirim. |
| Ekspor personel | Akses baca dalam cakupan dan peran Ekspor personel. |
| Buat kasus | Kapabilitas pencipta dari jalur CLI dan cakupan satuan penanggung jawab. Sistem memberi grant baca, tulis, dan ajukan hanya untuk kasus yang baru dibuat. |
| Baca dan kelola kasus | Grant baca serta tulis/ajukan/verifikasi sesuai tindakan. Verifikasi tetap memerlukan aktor berbeda. |
| Unduh atau ekspor kasus | Grant baca dan grant unduh atau ekspor langsung pada kasus. |
| Kelola grant kasus | Pejabat penugasan hasil bootstrap, selalu untuk akun lain. Akun administrator tidak otomatis membaca isi kasus. |
| Penautan pihak di satuan lain | Kapabilitas lintas satuan yang ditetapkan secara eksplisit melalui CLI. Pembacaan pihak di layar kasus tidak memberikan akses profil umum. |
| Buka dan tandai notifikasi | Akun penerima dan akses objek masih berlaku. Versi perkiraan dan aturan harus tetap berlaku untuk pengingat pensiun. |

Aplikasi membaca peran, scope, dan grant dari basis data saat digunakan. Mutasi verifikasi mengunci akun yang terlibat dan membaca ulang izin. Berkas hanya disajikan sebagai unduhan oleh controller. Pemilik akun hosting dan operator database tetap merupakan batas kepercayaan operasional. Perubahan kata sandi akun kasus oleh administrator web dibatasi, dan pemulihan akun sensitif dijalankan melalui prosedur CLI.

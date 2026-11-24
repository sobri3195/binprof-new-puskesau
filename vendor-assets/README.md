# Aset peta lokal

Paket runtime menyertakan Leaflet 1.9.4 berlisensi BSD 2-Clause dan garis wilayah Natural Earth ranah publik yang berasal dari world-atlas 2.0.2. Lisensi distribusi disertakan. Untuk membangun ulang, jalankan `npm ci --ignore-scripts` di direktori ini, salin `dist/leaflet.js`, `dist/leaflet.css`, `dist/images/` dan lisensi Leaflet ke direktori aset, lalu jalankan `python3 build-outline.py`. Node dan Python hanya dipakai untuk membangun ulang aset.

Garis wilayah sederhana dipakai sebagai latar visual. Penanda membutuhkan koordinat satuan yang tersimpan. Penyedia tile opsional harus dikonfigurasi bersama atribusi yang diwajibkan. Paket tidak memasukkan tile dari penyedia pihak ketiga.

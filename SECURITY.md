# Kebijakan Keamanan

## Versi yang Didukung

| Versi | Didukung |
| --- | --- |
| 1.0.x | Ya |
| < 1.0 | Tidak |

## Melaporkan Kerentanan

Kirim laporan kerentanan secara **pribadi** ke:

- **mahakammoonlightstudio@gmail.com**

Jangan buat issue publik untuk kerentanan keamanan. Sertakan deskripsi, langkah reproduksi, versi yang terdampak, dan saran perbaikan bila ada.

Respons diupayakan dalam **7 hari kerja**. Setelah perbaikan dirilis, pelapor dipersilakan mengungkapkan temuan secara publik dengan kredit.

## Cakupan

Berlaku untuk kode di repositori ini:

- Injeksi SQL, XSS, CSRF;
- Bypass anti-duplikat (cookie/IP/voter_id);
- Kebocoran data penilaian atau IP pengunjung;
- Kelemahan sesi atau proteksi halaman admin.

Di luar cakupan: konfigurasi server/hosting (InfinityFree) dan serangan brute force skala besar.

## Panduan Deploy Aman

- Jalankan di atas HTTPS; session cookie otomatis `secure` bila HTTPS aktif.
- `config.local.php` tidak boleh ikut ke repositori - ganti `ADMIN_PASSWORD` default segera.
- Dump database live (`if0_*.sql`) berisi IP asli pengunjung - jangan pernah dibagikan.

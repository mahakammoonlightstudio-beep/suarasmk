# Suara SMKN 1 Tenggarong

> Beri penilaian anonim untuk OSIS, MPK, dan ekstrakurikuler di SMKN 1 Tenggarong.

**Suara SMK** adalah aplikasi web penilaian organisasi siswa yang dibangun dengan **PHP native + MySQL/MariaDB (PDO)** — ringan, tanpa framework, dan siap dijalankan di shared hosting seperti InfinityFree.

## ✨ Fitur

- **Penilaian anonim** — siswa memberi rating bintang + komentar untuk tiap organisasi (OSIS, MPK, ekstrakurikuler).
- **Mode anti-duplikat** — 1 suara per hari per perangkat, bisa diatur per `cookie`, per `IP`, atau keduanya (`DUPLICATE_MODE` di `config.php`).
- **Admin panel** (`admin.php`) — kelola organisasi, lihat hasil, export CSV, reset data; dilindungi password (`ADMIN_PASSWORD`).
- **Filter kata kasar** — komentar otomatis disensor (daftar kata bisa ditambah di `config.php`).
- **Keamanan** — CSRF token, session hardening (httponly/samesite), prepared statements PDO, sensor sebagian IP di dashboard.
- **PWA** — installable & offline-ready (`manifest.json` + `sw.js`).

## 🛠️ Teknologi

- PHP 7.2+ (native, tanpa framework)
- MySQL / MariaDB (PDO)
- Vanilla JavaScript, CSS murni
- Service Worker + Web App Manifest (PWA)

## 🚀 Menjalankan Secara Lokal (XAMPP)

1. Salin folder ini ke `htdocs/`.
2. Buat database baru, lalu import `database.sql` via phpMyAdmin.
3. Buat file `config.local.php` di root proyek (file ini sengaja **tidak** ikut di repo — lihat `.gitignore`):

   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'user_db');
   define('DB_PASS', 'password_db');
   define('DB_NAME', 'nama_db');
   define('ADMIN_PASSWORD', 'password_admin_yang_kuat');
   ```

4. Buka `http://localhost/suarasmk/` — selesai.

## 📁 Struktur Utama

```
suarasmk/
├── index.php            # Halaman utama: daftar organisasi & form penilaian
├── admin.php            # Panel admin (password: ADMIN_PASSWORD)
├── org.php              # Detail & hasil penilaian per organisasi
├── rate.php             # Proses simpan penilaian
├── export.php           # Export hasil ke CSV
├── config.php           # Bootstrap: session, CSRF, koneksi DB, helper
├── config.local.php     # Kredensial (TIDAK di-commit — buat sendiri)
├── database.sql         # Skema + seed database
├── reset-data.sql       # Skrip reset data organisasi (opsional)
├── upgrade-voter-id.sql # Migrasi: kolom voter_id untuk anti-duplikat
└── sw.js                # Service worker (PWA)
```

## ☁️ Deploy ke InfinityFree

1. Upload semua file ke `htdocs/` (kecuali `config.local.php` — buat langsung di server).
2. Buat database MySQL di panel, import `database.sql` lalu jalankan `upgrade-voter-id.sql`.
3. Isi `config.local.php` dengan kredensial dari panel.
4. Ganti `ADMIN_PASSWORD` default segera setelah deploy.

## 🔒 Catatan Keamanan

- `config.local.php` dan dump database live (`if0_*.sql`) **tidak pernah di-commit** — sudah dicegah lewat `.gitignore`.
- Dump database live berisi IP asli pengunjung — jangan pernah dibagikan atau di-commit.

---

Hak cipta © 2026 **Mahakam Moonlight Studio**. Dibuat untuk SMKN 1 Tenggarong.

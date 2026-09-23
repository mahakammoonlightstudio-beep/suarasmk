# Suara SMKN 1 Tenggarong

Aplikasi web penilaian organisasi siswa untuk SMKN 1 Tenggarong. Siswa dapat memberi rating dan komentar anonim untuk OSIS, MPK, dan ekstrakurikuler, sedangkan panitia memantau hasil melalui panel admin. Dibangun dengan PHP native dan MySQL tanpa framework, ringan untuk dijalankan di shared hosting.

**Live demo: <https://suara-smkn1tgr.infinityfreeapp.com/>**

![PHP](https://img.shields.io/badge/PHP-7.2%2B-777bb3) ![MySQL](https://img.shields.io/badge/DB-MySQL%20%2F%20MariaDB-4479a1) ![PWA](https://img.shields.io/badge/PWA-ready-5a0fc8)

## Fitur

- **Penilaian anonim** - rating bintang dan komentar untuk tiap organisasi, dengan sensor kata kasar otomatis (daftar kata dapat dikonfigurasi).
- **Mode anti-duplikat** - satu suara per hari per perangkat; dapat diatur per cookie, per IP, atau keduanya melalui konstanta `DUPLICATE_MODE`.
- **Panel admin** - kelola organisasi, lihat hasil, export CSV, dan reset data; dilindungi password `ADMIN_PASSWORD`.
- **Keamanan** - CSRF token, session hardening (httponly, samesite), prepared statements PDO, dan sensor sebagian IP pada dashboard.
- **PWA** - dapat dipasang dan berfungsi offline melalui manifest dan service worker.

## Persyaratan

- PHP 7.2 atau lebih baru dengan ekstensi `pdo_mysql`
- MySQL 5.7 / MariaDB 10.4 atau lebih baru

## Instalasi

1. Salin proyek ke folder web server:

   ```bash
   git clone https://github.com/mahakammoonlightstudio-beep/suarasmk.git
   ```

2. Buat database baru dan impor skema:

   ```bash
   mysql -u USER -p NAMA_DATABASE < database.sql
   ```

   Untuk database yang sudah berjalan, jalankan pula `upgrade-voter-id.sql` untuk migrasi anti-duplikat per perangkat.

3. Buat berkas `config.local.php` di root proyek. Berkas ini sengaja tidak ikut di repositori (lihat `.gitignore`):

   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'user_db');
   define('DB_PASS', 'password_db');
   define('DB_NAME', 'nama_db');
   define('ADMIN_PASSWORD', 'password_admin_yang_kuat');
   ```

4. Buka aplikasi di browser dan segera ganti `ADMIN_PASSWORD` bila masih memakai nilai default.

## Menjalankan secara lokal

PHP bawaan cukup untuk pengembangan:

```bash
php -S localhost:8000
```

Pengguna Laravel Herd (macOS/Windows) dapat memakai biner PHP yang terpasang, misalnya `~/.config/herd/bin/php84/php.exe` pada Windows.

## Struktur Proyek

```
suarasmk/
├── index.php             # Halaman utama: daftar organisasi dan form penilaian
├── admin.php             # Panel admin (export CSV, reset data)
├── org.php               # Detail dan hasil penilaian per organisasi
├── rate.php              # Handler penyimpanan penilaian
├── export.php            # Export hasil ke CSV
├── config.php            # Bootstrap: session, CSRF, koneksi DB, helper
├── config.local.php      # Kredensial (tidak di-commit; buat sendiri)
├── database.sql          # Skema dan seed database
├── reset-data.sql        # Skrip reset data organisasi (opsional)
├── upgrade-voter-id.sql  # Migrasi kolom voter_id untuk anti-duplikat
└── sw.js                 # Service worker (PWA)
```

## Catatan Keamanan

- `config.local.php` dan dump database live (`if0_*.sql`) tidak pernah di-commit; keduanya dicegah lewat `.gitignore`.
- Dump database live memuat IP asli pengunjung dan tidak boleh dibagikan.

## Lisensi

Hak cipta 2026 Mahakam Moonlight Studio. Dibuat untuk SMKN 1 Tenggarong.

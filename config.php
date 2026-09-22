<?php
// ================================================================
//  SUARA SMKN 1 TENGGARONG — Konfigurasi Utama
//  Kredensial database ada di config.local.php (jangan di-commit).
// ================================================================

// --- Muat kredensial rahasia ---
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    http_response_code(500);
    die('<div style="font-family:sans-serif;padding:2rem;max-width:560px;margin:3rem auto;color:#dc2626;background:#fef2f2;border:1px solid #fecaca;border-radius:12px">
        <h2>❌ config.local.php tidak ditemukan</h2>
        <p style="color:#334155">Buat file <code>config.local.php</code> di folder yang sama dengan isi:</p>
        <pre style="background:#1e293b;color:#e2e8f0;padding:1rem;border-radius:8px;overflow:auto">&lt;?php
define(\'DB_HOST\', \'localhost\');
define(\'DB_USER\', \'user_db\');
define(\'DB_PASS\', \'password_db\');
define(\'DB_NAME\',  \'nama_db\');
define(\'ADMIN_PASSWORD\', \'password_admin_yang_kuat\');</pre>
    </div>');
}

// --- Pengaturan situs ---
define('SITE_NAME', 'Suara SMKN 1 Tenggarong');

// Mode anti-duplikat penilaian (1 suara per hari per perangkat):
//   'cookie' = per browser (default — cocok jika satu WiFi/IP untuk banyak siswa)
//   'ip'     = per alamat IP (pakai jika tiap siswa punya IP sendiri)
//   'both'   = cookie DAN IP harus belum menilai hari ini
define('DUPLICATE_MODE', 'cookie');

// Waktu server → WITA (Tenggarong, Kaltim)
date_default_timezone_set('Asia/Makassar');

// ================================================================
//  JANGAN EDIT DI BAWAH SINI
// ================================================================

// --- Session hardening ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

// --- Koneksi database ---
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('<div style="font-family:sans-serif;padding:2rem;color:red">
        <h2>❌ Koneksi Database Gagal</h2>
        <p>Cek kembali isi file <code>config.local.php</code> — DB_HOST, DB_USER, DB_PASS, DB_NAME.</p>
        <small>' . htmlspecialchars($e->getMessage()) . '</small>
    </div>');
}

// ================================================================
//  HELPER
// ================================================================

/** Escape output HTML */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Token CSRF untuk form */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input CSRF untuk ditempel di dalam <form> */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/** Verifikasi token CSRF dari POST */
function csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent)
        && $sent !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/**
 * Ambil IP asli pengunjung (mendukung hosting di belakang proxy/CDN).
 * X-Forwarded-For bisa dipalsukan, jadi hanya dipakai untuk statistik,
 * bukan pemblokiran (kecuali DUPLICATE_MODE = 'ip').
 */
function get_client_ip(): string
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/** Sensor sebagian IP untuk ditampilkan di dashboard/export */
function censor_ip(string $ip): string
{
    $parts = explode('.', $ip);
    if (count($parts) === 4) {
        return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
    }
    return substr($ip, 0, 6) . '…';
}

/** Redirect ke index.php dengan status alert */
function redirect_status(string $type, string $msg = ''): void
{
    $url = 'index.php?status=' . urlencode($type);
    if ($msg !== '') {
        $url .= '&msg=' . urlencode($msg);
    }
    header('Location: ' . $url);
    exit;
}

/** Ikon bintang sesuai rata-rata (mendukung setengah bintang) */
function render_stars(float $avg): string
{
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($avg)) {
            $html .= '<i class="fas fa-star star-gold"></i>';
        } elseif ($i - 0.5 <= $avg) {
            $html .= '<i class="fas fa-star-half-alt star-gold"></i>';
        } else {
            $html .= '<i class="far fa-star star-empty"></i>';
        }
    }
    return $html;
}

// ---- FILTER KATA KASAR (bisa ditambah sendiri) ----
$GLOBALS['BAD_WORDS'] = [
    'anjing', 'bangsat', 'bajingan', 'tai', 'tolol', 'goblok', 'bego',
    'babi', 'monyet', 'asu', 'kampret', 'setan', 'sialan', 'brengsek',
    'ngentot', 'jembut', 'pepek', 'kontol', 'memek', 'tetek', 'lonte',
    'gigolo', 'bencong', 'banci',
];

/**
 * Bersihkan komentar: buang tag HTML, sensor kata kasar,
 * rapikan spasi, batasi 500 karakter.
 */
function clean_comment(string $text): string
{
    $text = strip_tags($text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

    foreach ($GLOBALS['BAD_WORDS'] as $word) {
        if ($word === '') {
            continue;
        }
        $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
        $text = preg_replace($pattern, str_repeat('*', max(3, mb_strlen($word))), $text);
    }

    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (mb_strlen($text) > 500) {
        $text = mb_substr($text, 0, 500);
    }
    return $text;
}

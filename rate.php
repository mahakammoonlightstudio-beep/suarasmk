<?php
require_once __DIR__ . '/config.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ---- 1. Verifikasi CSRF ----
if (!csrf_verify()) {
    redirect_status('error', 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.');
}

// ---- 2. Ambil & validasi input ----
$orgId  = isset($_POST['org_id']) ? intval($_POST['org_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment = clean_comment((string)($_POST['comment'] ?? ''));

if ($orgId <= 0 || $rating < 1 || $rating > 5) {
    redirect_status('error', 'Data tidak valid.');
}

// ---- 3. Cek organisasi ada ----
$stmtOrg = $pdo->prepare("SELECT id FROM organizations WHERE id = ?");
$stmtOrg->execute([$orgId]);
if (!$stmtOrg->fetch()) {
    redirect_status('error', 'Organisasi tidak ditemukan.');
}

// ---- 4. Identitas voter: cookie random 90 hari ----
$voterId = $_COOKIE['voter_id'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $voterId)) {
    $voterId = bin2hex(random_bytes(16));
}
setcookie('voter_id', $voterId, [
    'expires'  => time() + 86400 * 90,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);

$userIP = get_client_ip();
$today  = date('Y-m-d');

// ---- 5. Cek duplikat sesuai mode ----
$mode = DUPLICATE_MODE;

if ($mode === 'cookie' || $mode === 'both') {
    $stmtDup = $pdo->prepare(
        "SELECT id FROM ratings WHERE org_id = ? AND voter_id = ? AND DATE(created_at) = CURDATE() LIMIT 1"
    );
    $stmtDup->execute([$orgId, $voterId]);
    if ($stmtDup->fetch()) {
        redirect_status('error', 'Kamu sudah menilai organisasi ini hari ini. Coba lagi besok!');
    }
}

if ($mode === 'ip' || $mode === 'both') {
    $stmtDupIp = $pdo->prepare(
        "SELECT id FROM ratings WHERE org_id = ? AND voter_ip = ? AND DATE(created_at) = CURDATE() LIMIT 1"
    );
    $stmtDupIp->execute([$orgId, $userIP]);
    if ($stmtDupIp->fetch()) {
        redirect_status('error', 'Perangkat/IP ini sudah menilai organisasi ini hari ini. Coba lagi besok!');
    }
}

// ---- 6. Lapisan tambahan: session per hari ----
$sessionKey = 'rated_' . $orgId;
if (($_SESSION[$sessionKey] ?? '') === $today) {
    redirect_status('error', 'Kamu sudah menilai organisasi ini hari ini. Coba lagi besok!');
}

// ---- 7. Simpan ----
$stmtInsert = $pdo->prepare(
    "INSERT INTO ratings (org_id, rating, comment, voter_ip, voter_id) VALUES (?, ?, ?, ?, ?)"
);
$stmtInsert->execute([$orgId, $rating, ($comment !== '' ? $comment : null), $userIP, $voterId]);

$_SESSION[$sessionKey] = $today;

redirect_status('success');

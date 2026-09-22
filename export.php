<?php
require_once __DIR__ . '/config.php';

// ---- Wajib login admin ----
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;color:#dc2626;text-align:center;margin-top:4rem">🚫 Akses ditolak</h2>
         <p style="text-align:center;font-family:sans-serif"><a href="admin.php">Login admin dulu</a></p>');
}

$format = ($_GET['format'] ?? 'csv') === 'json' ? 'json' : 'csv';
$orgId  = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;

// ---- Ambil data ----
$where  = $orgId > 0 ? 'WHERE r.org_id = ?' : '';
$params = $orgId > 0 ? [$orgId] : [];

$stmt = $pdo->prepare("
    SELECT r.id, o.name AS org_name, o.category, r.rating, r.comment,
           r.voter_ip, r.created_at
    FROM ratings r
    JOIN organizations o ON r.org_id = o.id
    {$where}
    ORDER BY r.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$stamp = date('Ymd_His');
$suffix = $orgId > 0 ? '_org' . $orgId : '';

// ---- JSON ----
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan' . $suffix . '_' . $stamp . '.json"');
    echo json_encode([
        'generated_at' => date('c'),
        'site'         => SITE_NAME,
        'total'        => count($rows),
        'data'         => $rows,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- CSV ----
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="laporan' . $suffix . '_' . $stamp . '.csv"');

$out = fopen('php://output', 'w');

// BOM UTF-8 agar dibaca benar oleh Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['ID', 'Organisasi', 'Kategori', 'Rating (1-5)', 'Komentar', 'IP (disensor)', 'Waktu']);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'],
        $r['org_name'],
        $r['category'],
        $r['rating'],
        $r['comment'],
        censor_ip((string)$r['voter_ip']),
        $r['created_at'],
    ]);
}

fclose($out);
exit;

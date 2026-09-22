<?php
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// ---- Ambil organisasi ----
$stmtOrg = $pdo->prepare("SELECT * FROM organizations WHERE id = ?");
$stmtOrg->execute([$id]);
$org = $stmtOrg->fetch();

if (!$org) {
    http_response_code(404);
} else {
    // ---- Ringkasan rating ----
    $stmtSummary = $pdo->prepare("
        SELECT COALESCE(ROUND(AVG(rating), 2), 0) AS avg_rating,
               COUNT(*) AS total_votes,
               COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS today_votes
        FROM ratings WHERE org_id = ?
    ");
    $stmtSummary->execute([$id]);
    $summary = $stmtSummary->fetch();

    $avg = (float)$summary['avg_rating'];
    $total = (int)$summary['total_votes'];
    $ratingClass = $avg >= 4 ? 'good' : ($avg >= 3 ? 'ok' : ($avg > 0 ? 'bad' : ''));

    // ---- Distribusi bintang ----
    $dist = ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];
    if ($total > 0) {
        $stmtDist = $pdo->prepare("SELECT rating, COUNT(*) AS cnt FROM ratings WHERE org_id = ? GROUP BY rating");
        $stmtDist->execute([$id]);
        foreach ($stmtDist->fetchAll() as $row) {
            $dist[(string)$row['rating']] = (int)$row['cnt'];
        }
    }

    // ---- Tren harian 30 hari terakhir ----
    $trend = $pdo->prepare("
        SELECT DATE(created_at) AS d, ROUND(AVG(rating), 2) AS avg_rating, COUNT(*) AS cnt
        FROM ratings
        WHERE org_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY d ASC
    ");
    $trend->execute([$id]);
    $trendRows = $trend->fetchAll();

    // ---- Semua komentar (pagination) ----
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ratings WHERE org_id = ? AND comment IS NOT NULL AND TRIM(comment) != ''");
    $stmtCount->execute([$id]);
    $totalComments = (int)$stmtCount->fetchColumn();
    $totalPages = max(1, (int)ceil($totalComments / $perPage));

    $stmtComments = $pdo->prepare("
        SELECT rating, comment, created_at
        FROM ratings
        WHERE org_id = ? AND comment IS NOT NULL AND TRIM(comment) != ''
        ORDER BY created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ");
    $stmtComments->execute([$id]);
    $comments = $stmtComments->fetchAll();

    // ---- Status vote pengguna hari ini ----
    $voterId = $_COOKIE['voter_id'] ?? '';
    $userIP  = get_client_ip();
    $mode    = DUPLICATE_MODE;
    $dupConds = [];
    $dupParams = [];
    if ($mode === 'cookie' || $mode === 'both') {
        $dupConds[] = 'voter_id = ?';
        $dupParams[] = $voterId;
    }
    if ($mode === 'ip' || $mode === 'both') {
        $dupConds[] = 'voter_ip = ?';
        $dupParams[] = $userIP;
    }
    $hasVoted = false;
    if ($dupConds) {
        $stmtVoted = $pdo->prepare(
            "SELECT id FROM ratings WHERE org_id = ? AND DATE(created_at) = CURDATE() AND (" . implode(' OR ', $dupConds) . ") LIMIT 1"
        );
        $stmtVoted->execute(array_merge([$id], $dupParams));
        $hasVoted = (bool)$stmtVoted->fetch();
    }
}

/** Bintang kecil untuk komentar */
function render_stars_small(int $rating): string
{
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<i class="' . ($i <= $rating ? 'fas' : 'far') . ' fa-star star-gold-sm"></i>';
    }
    return $html;
}

/** Label relatif waktu */
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)        return 'baru saja';
    if ($diff < 3600)      return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400)     return floor($diff / 3600) . ' jam lalu';
    if ($diff < 2592000)   return floor($diff / 86400) . ' hari lalu';
    return date('d M Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" id="metaThemeColor" content="#1a56db">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Suara SMK">
    <?php if ($org): ?>
    <title><?= e($org['name']) ?> — Penilaian & Ulasan | <?= e(SITE_NAME) ?></title>
    <?php else: ?>
    <title>Organisasi Tidak Ditemukan — <?= e(SITE_NAME) ?></title>
    <?php endif; ?>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📢</text></svg>">
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/img/icon.svg">
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-dark navbar-main">
    <div class="container">
        <a class="navbar-brand fw-bold fs-5" href="index.php">
            <i class="fas fa-bullhorn me-2"></i><?= e(SITE_NAME) ?>
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-nav">Beta</span>
            <button type="button" class="mode-toggle" id="modeToggle" aria-label="Ganti tema gelap/terang">
                <i class="fas fa-moon"></i>
            </button>
        </div>
    </div>
</nav>

<?php if (!$org): ?>
<!-- ===== 404 ===== -->
<div class="container py-5 text-center">
    <div class="empty-state">
        <div class="empty-emoji">🤷</div>
        <h4 class="fw-bold">Organisasi tidak ditemukan</h4>
        <p class="text-muted">Mungkin sudah dihapus atau alamatnya salah.</p>
        <a href="index.php" class="btn btn-rate"><i class="fas fa-arrow-left me-2"></i>Kembali ke Beranda</a>
    </div>
</div>

<?php else: ?>
<div class="container py-5">

    <a href="index.php" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i>Kembali ke Beranda</a>

    <!-- ===== HEADER ORGANISASI ===== -->
    <div class="org-card mt-3">
        <div class="org-head">
            <span class="org-emoji"><?= $org['logo_emoji'] ?></span>
            <div>
                <h1 class="org-name"><?= e($org['name']) ?></h1>
                <span class="org-badge"><?= e($org['category']) ?></span>
            </div>
        </div>
        <p class="org-desc"><?= e($org['description']) ?></p>

        <div class="row g-3 align-items-center">
            <div class="col-sm-4">
                <div class="rating-block h-100 text-center">
                    <div class="big-num <?= $ratingClass ?>"><?= $total > 0 ? number_format($avg, 2) : '—' ?></div>
                    <div class="my-2"><?= render_stars((float)$avg) ?></div>
                    <small class="text-muted"><?= number_format($total) ?> penilaian total</small>
                </div>
            </div>
            <div class="col-sm-8">
                <div class="rating-block h-100">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <div class="bar-row">
                        <span class="bar-lbl"><?= $i ?>★</span>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:<?= $total > 0 ? round($dist[(string)$i] / $total * 100) : 0 ?>%"></div>
                        </div>
                        <span class="bar-cnt"><?= $dist[(string)$i] ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <?php if ($hasVoted): ?>
                <button class="btn btn-voted flex-grow-1" disabled>
                    <i class="fas fa-check-circle me-2"></i>Sudah Dinilai Hari Ini
                </button>
            <?php else: ?>
                <button class="btn btn-rate flex-grow-1"
                        data-bs-toggle="modal"
                        data-bs-target="#rateModal"
                        data-org-id="<?= (int)$org['id'] ?>"
                        data-org-name="<?= e($org['name']) ?>">
                    <i class="fas fa-star me-2"></i>Beri Penilaian
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== TREN RATING ===== -->
    <?php if (count($trendRows) > 0): ?>
    <div class="section-header mt-5 mb-3">
        <span class="section-bar"></span>
        <h2 class="section-title">📈 Tren Penilaian (30 Hari)</h2>
    </div>
    <div class="org-card">
        <div class="chart-wrap">
            <canvas id="trendChart" height="110"></canvas>
        </div>
        <?php if (count($trendRows) < 2): ?>
        <p class="text-muted small text-center mb-0 mt-2">Grafik akan lebih menarik setelah ada lebih banyak penilaian. 📊</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== SEMUA KOMENTAR ===== -->
    <div class="section-header mt-5 mb-3">
        <span class="section-bar"></span>
        <h2 class="section-title">💬 Penilaian & Komentar (<?= number_format($totalComments) ?>)</h2>
    </div>

    <?php if (empty($comments)): ?>
    <div class="empty-state">
        <div class="empty-emoji">📝</div>
        <h5>Belum ada komentar</h5>
        <p class="text-muted mb-0">Jadilah yang pertama memberi masukan!</p>
    </div>
    <?php else: ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($comments as $c): ?>
        <div class="comment-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <?= render_stars_small((int)$c['rating']) ?>
                    <small class="text-muted ms-2"><?= e(time_ago($c['created_at'])) ?></small>
                </div>
                <small class="text-muted"><?= date('d M Y H:i', strtotime($c['created_at'])) ?></small>
            </div>
            <p class="comment-text mb-0">"<?= e($c['comment']) ?>"</p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="?id=<?= $id ?>&page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a></li>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            if ($start > 1): ?>
            <li class="page-item"><a class="page-link" href="?id=<?= $id ?>&page=1">1</a></li>
            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <?php endif; ?>
            <?php for ($p = $start; $p <= $end; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?id=<?= $id ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="?id=<?= $id ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="?id=<?= $id ?>&page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

</div><!-- /container -->

<!-- ===== FOOTER ===== -->
<footer class="site-footer">
    <div class="container text-center">
        <p class="mb-1 fw-semibold"><?= e(SITE_NAME) ?></p>
        <small class="text-muted d-block mb-2">Semua penilaian bersifat anonim · 1 suara per hari per perangkat</small>
        <small><a href="admin.php" class="footer-link"><i class="fas fa-shield-halved me-1"></i>Admin Panel</a></small>
    </div>
</footer>

<!-- ===== RATING MODAL ===== -->
<div class="modal fade" id="rateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-custom">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">
                    Nilai <span id="modalOrgName" class="text-primary fw-bold"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">

                <form id="ratingForm" method="POST" action="rate.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="org_id"  id="formOrgId">
                    <input type="hidden" name="rating"  id="formRating">
                    <input type="hidden" name="comment" id="formComment">
                </form>

                <p class="text-muted mb-4">Seberapa baik kinerja organisasi ini menurutmu?</p>

                <div class="text-center mb-4">
                    <div class="star-picker" id="starPicker">
                        <i class="far fa-star star-pick" data-val="1"></i>
                        <i class="far fa-star star-pick" data-val="2"></i>
                        <i class="far fa-star star-pick" data-val="3"></i>
                        <i class="far fa-star star-pick" data-val="4"></i>
                        <i class="far fa-star star-pick" data-val="5"></i>
                    </div>
                    <div id="starLabel" class="star-label text-muted">Pilih bintang...</div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted small">Komentar / Saran <span class="text-muted">(opsional)</span></label>
                    <textarea id="ratingComment" class="form-control" rows="3" maxlength="500"
                              placeholder="Tulis kritik atau saran kamu di sini..."></textarea>
                    <div class="form-text">Maks. 500 karakter — kata kasar otomatis disensor.</div>
                </div>

                <button id="submitBtn" class="btn btn-rate w-100" disabled>
                    <i class="fas fa-paper-plane me-2"></i>Kirim Penilaian
                </button>

            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($org && count($trendRows) > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // Warna grafik mengikuti tema
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var gridColor = isDark ? 'rgba(148,163,184,.15)' : 'rgba(100,116,139,.15)';
    var tickColor = isDark ? '#94a3b8' : '#64748b';

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($r) => date('d M', strtotime($r['d'])), $trendRows)) ?>,
            datasets: [{
                label: 'Rata-rata rating',
                data: <?= json_encode(array_map(fn($r) => (float)$r['avg_rating'], $trendRows)) ?>,
                borderColor: '#1a56db',
                backgroundColor: 'rgba(26,86,219,.1)',
                fill: true,
                tension: .35,
                pointRadius: 4,
                pointBackgroundColor: '#1a56db'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 1, max: 5, ticks: { color: tickColor, stepSize: 1 }, grid: { color: gridColor } },
                x: { ticks: { color: tickColor }, grid: { display: false } }
            }
        }
    });
</script>
<?php endif; ?>
<script src="assets/js/main.js"></script>

<!-- ===== PWA & UX ===== -->
<button type="button" class="scroll-top" id="scrollTop" aria-label="Kembali ke atas"><i class="fas fa-arrow-up"></i></button>
<div class="toast-stack" id="toastStack" aria-live="polite"></div>
<div class="offline-indicator" id="offlineIndicator"><i class="fas fa-wifi me-2"></i>Kamu offline — cek koneksi internet kamu.</div>
<script src="assets/js/pwa.js"></script>
</body>
</html>

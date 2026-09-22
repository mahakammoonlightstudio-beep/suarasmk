<?php
require_once __DIR__ . '/config.php';

// ---- Filter / search / sort (GET) ----
$q      = trim((string)($_GET['q'] ?? ''));
$cat    = trim((string)($_GET['cat'] ?? ''));
$sort   = trim((string)($_GET['sort'] ?? ''));
$tab    = trim((string)($_GET['tab'] ?? ''));
$isDefaultView = ($q === '' && $cat === '' && $sort === '');

$orderByMap = [
    'rating' => 'avg_rating DESC, total_votes DESC',
    'votes'  => 'total_votes DESC, avg_rating DESC',
    'new'    => 'o.created_at DESC, o.id DESC',
    'name'   => 'o.name ASC',
];
$orderBy = $orderByMap[$sort] ?? 'o.id ASC';

// ---- Status alert dari redirect rate.php ----
$statusAlert = null;
if (!empty($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $statusAlert = ['type' => 'success', 'icon' => '✅', 'text' => 'Penilaian berhasil dikirim! Terima kasih sudah bersuara.'];
    } elseif ($_GET['status'] === 'error') {
        $statusAlert = ['type' => 'warning', 'icon' => '⚠️', 'text' => (string)($_GET['msg'] ?? 'Terjadi kesalahan.')];
    }
}

// ---- Statistik global (semua data, tidak terpengaruh filter) ----
$statsGlobal = $pdo->query(
    "SELECT COUNT(*) AS total_votes, COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating FROM ratings"
)->fetch();
$totalVotes = (int)$statsGlobal['total_votes'];
$globalAvg  = (float)$statsGlobal['avg_rating'];

// ---- Daftar kategori untuk dropdown ----
$categories = $pdo->query("SELECT DISTINCT category FROM organizations ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

// ---- Query organisasi + rating (dengan filter) ----
$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(o.name LIKE ? OR o.description LIKE ?)';
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
}
if ($cat !== '') {
    $where[] = 'o.category = ?';
    $params[] = $cat;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT
        o.*,
        COALESCE(ROUND(AVG(r.rating), 1), 0)    AS avg_rating,
        COUNT(r.id)                             AS total_votes,
        COUNT(CASE WHEN r.rating = 5 THEN 1 END) AS stars_5,
        COUNT(CASE WHEN r.rating = 4 THEN 1 END) AS stars_4,
        COUNT(CASE WHEN r.rating = 3 THEN 1 END) AS stars_3,
        COUNT(CASE WHEN r.rating = 2 THEN 1 END) AS stars_2,
        COUNT(CASE WHEN r.rating = 1 THEN 1 END) AS stars_1,
        MAX(DATE(r.created_at))                 AS last_rated
    FROM organizations o
    LEFT JOIN ratings r ON o.id = r.org_id
    {$whereSql}
    GROUP BY o.id
    ORDER BY {$orderBy}
");
$stmt->execute($params);
$organizations = $stmt->fetchAll();

// ---- Leaderboard: top 3 (hanya di tampilan default) ----
$leaderboard = [];
if ($isDefaultView) {
    $leaderboard = $pdo->query("
        SELECT o.id, o.name, o.logo_emoji, o.category,
               COALESCE(ROUND(AVG(r.rating), 1), 0) AS avg_rating,
               COUNT(r.id) AS total_votes
        FROM organizations o
        LEFT JOIN ratings r ON o.id = r.org_id
        GROUP BY o.id
        ORDER BY avg_rating DESC, total_votes DESC, o.name ASC
        LIMIT 3
    ")->fetchAll();
}

// ---- Komentar terbaru (6) ----
$recentComments = $pdo->query("
    SELECT r.rating, r.comment, r.created_at, r.org_id, o.name AS org_name
    FROM ratings r
    JOIN organizations o ON r.org_id = o.id
    WHERE r.comment IS NOT NULL AND TRIM(r.comment) != ''
    ORDER BY r.created_at DESC
    LIMIT 6
")->fetchAll();

// ---- Status vote pengguna hari ini (cookie + IP) ----
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
$ratedOrgIds = [];
if ($dupConds) {
    $stmtVoted = $pdo->prepare(
        "SELECT DISTINCT org_id FROM ratings WHERE DATE(created_at) = CURDATE() AND (" . implode(' OR ', $dupConds) . ")"
    );
    $stmtVoted->execute($dupParams);
    $ratedOrgIds = array_map('intval', array_column($stmtVoted->fetchAll(), 'org_id'));
}

// ---- Helper URL filter (dipakai di kartu & tab) ----
function filter_url(array $overrides = []): string
{
    $base = ['q' => $GLOBALS['q'], 'cat' => $GLOBALS['cat'], 'sort' => $GLOBALS['sort']];
    $merged = array_merge($base, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return 'index.php' . ($merged ? '?' . http_build_query($merged) : '');
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
    <title><?= e(SITE_NAME) ?> — Nilai Organisasi Sekolahmu</title>
    <meta name="description" content="Beri penilaian anonim untuk OSIS, MPK, dan ekstrakurikuler di <?= e(SITE_NAME) ?>.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📢</text></svg>">
    <script>
        // Terapkan tema sebelum render agar tidak flash
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

<!-- ===== HERO ===== -->
<section class="hero-section">
    <div class="container text-center">
        <div class="hero-icon mb-3">📢</div>
        <h1 class="hero-title">Suarakan Pendapatmu!</h1>
        <p class="hero-sub">Beri penilaian jujur untuk OSIS & MPK sekolah kita.<br>Anonim, transparan, dan bermakna.</p>
        <div class="hero-actions">
            <button type="button" class="btn-hero" id="shareBtn"><i class="fas fa-share-nodes me-2"></i>Bagikan</button>
            <button type="button" class="btn-hero btn-hero-ghost" id="installBtn"><i class="fas fa-download me-2"></i>Install App</button>
        </div>
    </div>
</section>

<div class="container py-5">

    <!-- ===== STATUS ALERT ===== -->
    <?php if ($statusAlert): ?>
    <div id="statusAlert" class="alert alert-<?= $statusAlert['type'] ?> alert-dismissible d-flex align-items-center gap-2 mb-4 shadow-sm" role="alert">
        <span style="font-size:1.2rem"><?= $statusAlert['icon'] ?></span>
        <span><?= e($statusAlert['text']) ?></span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ===== STATS ROW ===== -->
    <div class="row g-3 mb-5">
        <div class="col-4">
            <div class="stat-card text-center">
                <div class="stat-emoji">🏆</div>
                <div class="stat-num"><?= count($organizations) ?></div>
                <div class="stat-lbl">Organisasi</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card text-center">
                <div class="stat-emoji">⭐</div>
                <div class="stat-num"><?= number_format($totalVotes) ?></div>
                <div class="stat-lbl">Total Votes</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card text-center">
                <div class="stat-emoji">📊</div>
                <div class="stat-num"><?= $globalAvg > 0 ? $globalAvg : '—' ?></div>
                <div class="stat-lbl">Rata-rata</div>
            </div>
        </div>
    </div>

    <!-- ===== LEADERBOARD ===== -->
    <?php if (!empty($leaderboard) && $totalVotes > 0): ?>
    <div class="section-header mb-3" id="leaderboard">
        <span class="section-bar"></span>
        <h2 class="section-title">🏅 Papan Peringkat</h2>
    </div>
    <div class="row g-3 mb-5">
        <?php $medals = ['🥇', '🥈', '🥉']; ?>
        <?php foreach ($leaderboard as $i => $lb): ?>
        <div class="col-md-4">
            <a href="org.php?id=<?= (int)$lb['id'] ?>" class="text-decoration-none">
                <div class="leader-card <?= $i === 0 ? 'leader-first' : '' ?>">
                    <div class="leader-rank"><?= $medals[$i] ?></div>
                    <div class="leader-emoji"><?= $lb['logo_emoji'] ?></div>
                    <div class="leader-body">
                        <div class="leader-name"><?= e($lb['name']) ?></div>
                        <div class="leader-meta">
                            <?= render_stars((float)$lb['avg_rating']) ?>
                            <span class="leader-score"><?= $lb['avg_rating'] > 0 ? $lb['avg_rating'] : '—' ?></span>
                            <span class="leader-votes"><?= number_format((int)$lb['total_votes']) ?> votes</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===== FILTER / SEARCH BAR ===== -->
    <div class="section-header mb-3">
        <span class="section-bar"></span>
        <h2 class="section-title">Nilai Organisasi</h2>
    </div>

    <form method="GET" action="index.php" class="filter-bar mb-4" id="filterForm">
        <div class="filter-search">
            <i class="fas fa-search"></i>
            <input type="text" name="q" class="form-control" placeholder="Cari organisasi..."
                   value="<?= e($q) ?>">
        </div>
        <select name="cat" class="form-select auto-submit">
            <option value="">Semua Kategori</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="sort" class="form-select auto-submit">
            <option value="">Urutkan: Default</option>
            <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Rating Tertinggi</option>
            <option value="votes"  <?= $sort === 'votes'  ? 'selected' : '' ?>>Paling Banyak Dinilai</option>
            <option value="new"    <?= $sort === 'new'    ? 'selected' : '' ?>>Terbaru</option>
            <option value="name"   <?= $sort === 'name'   ? 'selected' : '' ?>>Nama (A-Z)</option>
        </select>
        <button type="submit" class="btn btn-rate filter-btn">Terapkan</button>
        <?php if (!$isDefaultView): ?>
            <a href="index.php" class="btn btn-reset filter-btn" title="Reset filter">
                <i class="fas fa-rotate-left"></i>
            </a>
        <?php endif; ?>
    </form>

    <!-- ===== ORGANIZATION CARDS ===== -->
    <?php if (empty($organizations)): ?>
    <div class="empty-state">
        <div class="empty-emoji">🔍</div>
        <h5>Tidak ada organisasi yang cocok</h5>
        <p class="text-muted mb-0">Coba kata kunci lain atau <a href="index.php">reset filter</a>.</p>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($organizations as $org):
            $avg   = (float)$org['avg_rating'];
            $total = (int)$org['total_votes'];
            $hasVoted = in_array((int)$org['id'], $ratedOrgIds, true);
            $ratingClass = $avg >= 4 ? 'good' : ($avg >= 3 ? 'ok' : ($avg > 0 ? 'bad' : ''));
        ?>
        <div class="col-md-6">
            <div class="org-card">

                <!-- Header -->
                <div class="org-head">
                    <span class="org-emoji"><?= $org['logo_emoji'] ?></span>
                    <div>
                        <h3 class="org-name"><?= e($org['name']) ?></h3>
                        <span class="org-badge"><?= e($org['category']) ?></span>
                    </div>
                </div>

                <p class="org-desc"><?= e($org['description']) ?></p>

                <!-- Rating Display -->
                <div class="rating-block">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="big-num <?= $ratingClass ?>"><?= $avg > 0 ? $avg : '—' ?></span>
                        <div>
                            <div class="stars-row mb-1"><?= render_stars($avg) ?></div>
                            <small class="text-muted"><?= number_format($total) ?> penilaian</small>
                        </div>
                    </div>

                    <!-- Breakdown bars -->
                    <?php if ($total > 0): ?>
                    <div class="breakdown-bars">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div class="bar-row">
                            <span class="bar-lbl"><?= $i ?>★</span>
                            <div class="bar-track">
                                <div class="bar-fill" style="width:<?= round($org['stars_' . $i] / $total * 100) ?>%"></div>
                            </div>
                            <span class="bar-cnt"><?= $org['stars_' . $i] ?></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2">
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
                    <a href="org.php?id=<?= (int)$org['id'] ?>" class="btn btn-detail" title="Lihat semua penilaian">
                        <i class="fas fa-comments"></i>
                    </a>
                </div>

            </div><!-- /org-card -->
        </div>
        <?php endforeach; ?>
    </div><!-- /row -->
    <?php endif; ?>

    <!-- ===== RECENT COMMENTS ===== -->
    <?php if (!empty($recentComments)): ?>
    <div class="section-header mt-5 mb-4">
        <span class="section-bar"></span>
        <h2 class="section-title">💬 Komentar Terbaru</h2>
    </div>
    <div class="row g-3">
        <?php foreach ($recentComments as $c): ?>
        <div class="col-md-6 col-lg-4">
            <div class="comment-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="comment-org"><?= e($c['org_name']) ?></span>
                    <div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="<?= $i <= $c['rating'] ? 'fas' : 'far' ?> fa-star star-gold-sm"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <p class="comment-text">"<?= e($c['comment']) ?>"</p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></small>
                    <a href="org.php?id=<?= (int)$c['org_id'] ?>" class="comment-link">Lihat semua →</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>

<!-- ===== PWA & UX ===== -->
<button type="button" class="scroll-top" id="scrollTop" aria-label="Kembali ke atas"><i class="fas fa-arrow-up"></i></button>
<div class="toast-stack" id="toastStack" aria-live="polite"></div>
<div class="offline-indicator" id="offlineIndicator"><i class="fas fa-wifi me-2"></i>Kamu offline — cek koneksi internet kamu.</div>
<script src="assets/js/pwa.js"></script>
</body>
</html>

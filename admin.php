<?php
require_once __DIR__ . '/config.php';

// ================================================================
//  POST ACTIONS
// ================================================================
$action = $_POST['action'] ?? '';
$flash  = trim((string)($_GET['msg'] ?? ''));
$flashType = ($_GET['type'] ?? 'success') === 'danger' ? 'danger' : 'success';

// ---- LOGIN ----
if ($action === 'login') {
    if (!csrf_verify()) {
        $loginError = 'Sesi tidak valid, muat ulang halaman.';
    } elseif (time() < ($_SESSION['login_lock_until'] ?? 0)) {
        $sisa = (int)ceil(($_SESSION['login_lock_until'] - time()) / 60);
        $loginError = "Terlalu banyak percobaan. Coba lagi dalam {$sisa} menit.";
    } elseif (($_POST['password'] ?? '') === ADMIN_PASSWORD) {
        unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_lock_until'] = time() + 300; // kunci 5 menit
            $_SESSION['login_attempts'] = 0;
            $loginError = 'Terlalu banyak percobaan salah. Login dikunci 5 menit.';
        } else {
            $loginError = 'Password salah!';
        }
    }
}

// ---- LOGOUT ----
if ($action === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']);

// ---- AKSI ADMIN (wajib login + CSRF) ----
if ($isLoggedIn && $action !== '') {
    if (!csrf_verify()) {
        $flash = 'Sesi tidak valid, aksi dibatalkan.';
        $flashType = 'danger';
    } else {
        switch ($action) {
            case 'add_org':
                $name = trim((string)($_POST['name'] ?? ''));
                $cat  = trim((string)($_POST['category'] ?? ''));
                $emo  = trim((string)($_POST['logo_emoji'] ?? '')) ?: '🏛️';
                $desc = trim((string)($_POST['description'] ?? ''));
                if ($name !== '') {
                    $st = $pdo->prepare("INSERT INTO organizations (name, description, logo_emoji, category) VALUES (?, ?, ?, ?)");
                    $st->execute([mb_substr($name, 0, 100), mb_substr($desc, 0, 2000), mb_substr($emo, 0, 10), mb_substr($cat ?: 'Organisasi', 0, 50)]);
                    $flash = "Organisasi \"{$name}\" berhasil ditambahkan.";
                } else {
                    $flash = 'Nama organisasi wajib diisi.'; $flashType = 'danger';
                }
                break;

            case 'edit_org':
                $id   = intval($_POST['org_id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $cat  = trim((string)($_POST['category'] ?? ''));
                $emo  = trim((string)($_POST['logo_emoji'] ?? '')) ?: '🏛️';
                $desc = trim((string)($_POST['description'] ?? ''));
                if ($id > 0 && $name !== '') {
                    $st = $pdo->prepare("UPDATE organizations SET name = ?, description = ?, logo_emoji = ?, category = ? WHERE id = ?");
                    $st->execute([mb_substr($name, 0, 100), mb_substr($desc, 0, 2000), mb_substr($emo, 0, 10), mb_substr($cat ?: 'Organisasi', 0, 50), $id]);
                    $flash = "Organisasi \"{$name}\" berhasil diperbarui.";
                } else {
                    $flash = 'Data tidak valid.'; $flashType = 'danger';
                }
                break;

            case 'delete_org':
                $id = intval($_POST['org_id'] ?? 0);
                $st = $pdo->prepare("SELECT name FROM organizations WHERE id = ?");
                $st->execute([$id]);
                $org = $st->fetch();
                if ($org) {
                    $stDel = $pdo->prepare("DELETE FROM organizations WHERE id = ?");
                    $stDel->execute([$id]);
                    $flash = "Organisasi \"{$org['name']}\" beserta semua penilaiannya dihapus.";
                }
                break;

            case 'delete_comment':
                $id = intval($_POST['rating_id'] ?? 0);
                $st = $pdo->prepare("DELETE FROM ratings WHERE id = ?");
                $st->execute([$id]);
                $flash = 'Penilaian berhasil dihapus.';
                break;
        }
        header('Location: admin.php?msg=' . urlencode($flash) . '&type=' . $flashType);
        exit;
    }
}

// ================================================================
//  DATA DASHBOARD
// ================================================================
if ($isLoggedIn) {
    // Ringkasan per organisasi
    $orgs = $pdo->query("
        SELECT o.*,
               COALESCE(ROUND(AVG(r.rating), 2), 0) AS avg_rating,
               COUNT(r.id) AS total_votes,
               COUNT(CASE WHEN DATE(r.created_at) = CURDATE() THEN 1 END) AS today_votes
        FROM organizations o
        LEFT JOIN ratings r ON o.id = r.org_id
        GROUP BY o.id
        ORDER BY o.id ASC
    ")->fetchAll();

    // 30 penilaian terbaru
    $recent = $pdo->query("
        SELECT r.*, o.name AS org_name
        FROM ratings r
        JOIN organizations o ON r.org_id = o.id
        ORDER BY r.created_at DESC
        LIMIT 30
    ")->fetchAll();

    // Total keseluruhan
    $totalAll = array_sum(array_column($orgs, 'total_votes'));
    $todayAll = array_sum(array_column($orgs, 'today_votes'));

    // Rata-rata global
    $avgAll = (float)$pdo->query("SELECT COALESCE(ROUND(AVG(rating), 2), 0) FROM ratings")->fetchColumn();

    // Votes 14 hari terakhir (isi hari kosong dengan 0)
    $votesPerDay = [];
    $stmtDays = $pdo->query("
        SELECT DATE(created_at) AS d, COUNT(*) AS c
        FROM ratings
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        GROUP BY DATE(created_at)
    ");
    $mapDays = [];
    foreach ($stmtDays->fetchAll() as $row) {
        $mapDays[$row['d']] = (int)$row['c'];
    }
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $votesPerDay[] = ['d' => date('d M', strtotime($d)), 'c' => $mapDays[$d] ?? 0];
    }

    // Distribusi rating semua waktu
    $distAll = ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];
    foreach ($pdo->query("SELECT rating, COUNT(*) AS c FROM ratings GROUP BY rating")->fetchAll() as $row) {
        $distAll[(string)$row['rating']] = (int)$row['c'];
    }
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
    <title>Admin Panel — <?= e(SITE_NAME) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔐</text></svg>">
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

<?php if (!$isLoggedIn): ?>
<!-- ===== LOGIN ===== -->
<div class="container">
    <div class="login-wrap">
        <div class="card-admin text-center">
            <div style="font-size:3rem" class="mb-2">🔐</div>
            <h4 class="fw-bold mb-1">Admin Panel</h4>
            <p class="text-muted mb-4"><?= e(SITE_NAME) ?></p>
            <?php if (!empty($loginError)): ?>
                <div class="alert alert-danger py-2"><?= e($loginError) ?></div>
            <?php endif; ?>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">
                <input type="password" name="password" class="form-control mb-3"
                       placeholder="Password Admin" required autofocus>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Masuk
                </button>
            </form>
            <div class="mt-3">
                <a href="index.php" class="text-muted small">← Kembali ke halaman utama</a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ===== DASHBOARD ===== -->
<nav class="navbar navbar-dark navbar-admin mb-4">
    <div class="container">
        <span class="navbar-brand fw-bold">
            <i class="fas fa-shield-alt me-2"></i>Admin — <?= e(SITE_NAME) ?>
        </span>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="mode-toggle" id="modeToggle" aria-label="Ganti tema">
                <i class="fas fa-moon"></i>
            </button>
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-eye me-1"></i>Lihat Situs
            </a>
            <form method="POST" class="mb-0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </button>
            </form>
        </div>
    </div>
</nav>

<div class="container pb-5">

    <!-- Flash message -->
    <?php if ($flash !== ''): ?>
    <div class="alert alert-<?= $flashType ?> alert-dismissible d-flex align-items-center gap-2 shadow-sm" role="alert">
        <span><?= $flashType === 'danger' ? '⚠️' : '✅' ?></span>
        <span><?= e($flash) ?></span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistik Global -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-admin stat-mini">
                <div class="num"><?= count($orgs) ?></div>
                <div class="lbl">Organisasi</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-admin stat-mini">
                <div class="num"><?= number_format($totalAll) ?></div>
                <div class="lbl">Total Votes</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-admin stat-mini">
                <div class="num"><?= $todayAll ?></div>
                <div class="lbl">Hari Ini</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-admin stat-mini">
                <div class="num"><?= $avgAll > 0 ? $avgAll : '—' ?></div>
                <div class="lbl">Rata-rata</div>
            </div>
        </div>
    </div>

    <!-- Tombol aksi -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <button class="btn btn-rate" data-bs-toggle="modal" data-bs-target="#addOrgModal">
            <i class="fas fa-plus me-2"></i>Tambah Organisasi
        </button>
        <a href="export.php?format=csv" class="btn btn-detail"><i class="fas fa-file-csv me-2"></i>Export CSV</a>
        <a href="export.php?format=json" class="btn btn-detail"><i class="fas fa-file-code me-2"></i>Export JSON</a>
    </div>

    <!-- Grafik -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card-admin">
                <h6 class="fw-bold text-uppercase text-muted small mb-3">Votes 14 Hari Terakhir</h6>
                <div class="chart-wrap"><canvas id="votesChart" height="120"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card-admin">
                <h6 class="fw-bold text-uppercase text-muted small mb-3">Distribusi Rating</h6>
                <div class="chart-wrap"><canvas id="distChart" height="160"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Ringkasan per Organisasi (dengan edit/hapus/export) -->
    <div class="d-flex align-items-center mb-3">
        <h6 class="fw-bold text-uppercase text-muted small mb-0">Kelola Organisasi</h6>
    </div>
    <div class="row g-3 mb-4">
        <?php foreach ($orgs as $o): ?>
        <div class="col-md-6">
            <div class="card-admin">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span style="font-size:1.8rem"><?= $o['logo_emoji'] ?></span>
                    <div>
                        <strong><?= e($o['name']) ?></strong>
                        <span class="badge badge-osis ms-1"><?= e($o['category']) ?></span>
                    </div>
                    <div class="ms-auto d-flex gap-1">
                        <a href="org.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-detail" title="Lihat halaman publik">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="export.php?format=csv&org_id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-detail" title="Export CSV org ini">
                            <i class="fas fa-file-csv"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-edit-org" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#editOrgModal"
                                data-id="<?= (int)$o['id'] ?>"
                                data-name="<?= e($o['name']) ?>"
                                data-category="<?= e($o['category']) ?>"
                                data-emoji="<?= e($o['logo_emoji']) ?>"
                                data-description="<?= e($o['description']) ?>">
                            <i class="fas fa-pen"></i>
                        </button>
                        <?php $orgNameJs = e(addslashes($o['name'])); ?>
                        <form method="POST" class="mb-0"
                              onsubmit="return confirm('Hapus organisasi <?= $orgNameJs ?> beserta SEMUA penilaiannya? Tindakan ini tidak bisa dibatalkan!')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_org">
                            <input type="hidden" name="org_id" value="<?= (int)$o['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-del-org" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="d-flex gap-4 mb-2">
                    <div><span style="font-size:1.8rem;font-weight:800;color:var(--primary)"><?= $o['avg_rating'] ?></span> <small class="text-muted">/ 5.0</small></div>
                    <div><strong><?= number_format((int)$o['total_votes']) ?></strong> <small class="text-muted">total</small></div>
                    <div><strong><?= (int)$o['today_votes'] ?></strong> <small class="text-muted">hari ini</small></div>
                </div>
                <div class="progress" style="height:8px;border-radius:4px">
                    <div class="progress-bar bg-warning" style="width:<?= min(floatval($o['avg_rating']) / 5 * 100, 100) ?>%"></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabel Penilaian Terbaru -->
    <h6 class="fw-bold text-uppercase text-muted small mb-3">30 Penilaian Terbaru — Moderasi Komentar</h6>
    <div class="card-admin p-0" style="overflow:hidden">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Waktu</th>
                        <th>Organisasi</th>
                        <th>Rating</th>
                        <th>Komentar</th>
                        <th>IP (sensor)</th>
                        <th class="pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td class="ps-3 text-muted" style="white-space:nowrap">
                            <?= date('d/m/y H:i', strtotime($r['created_at'])) ?>
                        </td>
                        <td><?= e($r['org_name']) ?></td>
                        <td class="star-cell">
                            <?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?>
                        </td>
                        <td style="max-width:260px">
                            <?php if ($r['comment']): ?>
                                <span title="<?= e($r['comment']) ?>"><?= e(mb_strimwidth($r['comment'], 0, 60, '…')) ?></span>
                            <?php else: ?>
                                <em class="text-muted">—</em>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e(censor_ip((string)$r['voter_ip'])) ?></td>
                        <td class="pe-3">
                            <form method="POST" class="mb-0"
                                  onsubmit="return confirm('Hapus penilaian ini (<?= (int)$r['rating'] ?>★ — <?= e(addslashes($r['org_name'])) ?>)?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_comment">
                                <input type="hidden" name="rating_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-del-org" title="Hapus penilaian">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada penilaian.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /container -->

<!-- ===== MODAL TAMBAH ORGANISASI ===== -->
<div class="modal fade" id="addOrgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-custom">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_org">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus me-2"></i>Tambah Organisasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="100" placeholder="cth: Futsal Club">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-5">
                            <label class="form-label small fw-semibold">Emoji Logo</label>
                            <input type="text" name="logo_emoji" class="form-control text-center" maxlength="10" value="🏛️">
                        </div>
                        <div class="col-7">
                            <label class="form-label small fw-semibold">Kategori</label>
                            <input type="text" name="category" class="form-control" maxlength="50" value="Organisasi" list="catList">
                            <datalist id="catList">
                                <option value="Organisasi"><option value="Ekstrakurikuler">
                            </datalist>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="2000" placeholder="Deskripsi singkat organisasi..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-voted" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-rate"><i class="fas fa-save me-2"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL EDIT ORGANISASI ===== -->
<div class="modal fade" id="editOrgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-custom">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_org">
                <input type="hidden" name="org_id" id="editOrgId">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-pen me-2"></i>Edit Organisasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editOrgName" class="form-control" required maxlength="100">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-5">
                            <label class="form-label small fw-semibold">Emoji Logo</label>
                            <input type="text" name="logo_emoji" id="editOrgEmoji" class="form-control text-center" maxlength="10">
                        </div>
                        <div class="col-7">
                            <label class="form-label small fw-semibold">Kategori</label>
                            <input type="text" name="category" id="editOrgCategory" class="form-control" maxlength="50" list="catListEdit">
                            <datalist id="catListEdit">
                                <option value="Organisasi"><option value="Ekstrakurikuler">
                            </datalist>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deskripsi</label>
                        <textarea name="description" id="editOrgDesc" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-voted" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-rate"><i class="fas fa-save me-2"></i>Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // ==== Grafik admin ====
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var gridColor = isDark ? 'rgba(148,163,184,.15)' : 'rgba(100,116,139,.15)';
    var tickColor = isDark ? '#94a3b8' : '#64748b';

    var votesCtx = document.getElementById('votesChart');
    if (votesCtx) {
        new Chart(votesCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($votesPerDay, 'd')) ?>,
                datasets: [{
                    label: 'Votes',
                    data: <?= json_encode(array_column($votesPerDay, 'c')) ?>,
                    backgroundColor: 'rgba(26,86,219,.75)',
                    borderRadius: 6
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, color: tickColor }, grid: { color: gridColor } },
                    x: { ticks: { color: tickColor, maxRotation: 0 }, grid: { display: false } }
                }
            }
        });
    }

    var distCtx = document.getElementById('distChart');
    if (distCtx) {
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: ['5★', '4★', '3★', '2★', '1★'],
                datasets: [{
                    data: <?= json_encode(array_values($distAll)) ?>,
                    backgroundColor: ['#059669', '#65a30d', '#f59e0b', '#f97316', '#dc2626'],
                    borderWidth: 0
                }]
            },
            options: {
                plugins: { legend: { position: 'bottom', labels: { color: tickColor, usePointStyle: true } } }
            }
        });
    }
</script>
<?php endif; ?>

<script src="assets/js/main.js"></script>

<!-- ===== PWA & UX ===== -->
<button type="button" class="scroll-top" id="scrollTop" aria-label="Kembali ke atas"><i class="fas fa-arrow-up"></i></button>
<div class="toast-stack" id="toastStack" aria-live="polite"></div>
<script src="assets/js/pwa.js"></script>
</body>
</html>

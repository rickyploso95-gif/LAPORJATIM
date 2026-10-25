<?php
require_once __DIR__ . '/_layout.php';
$admin = require_admin();
$pdo = db();

$s = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'belum'), 0) AS belum, COALESCE(SUM(status = 'proses'), 0) AS proses, COALESCE(SUM(status = 'selesai'), 0) AS selesai FROM reports WHERE is_archived = 0")->fetch();
$archived = (int)$pdo->query('SELECT COUNT(*) FROM reports WHERE is_archived = 1')->fetchColumn();
$recent = $pdo->query('SELECT r.id, r.title, r.regency, r.status, r.created_at, c.name AS category FROM reports r JOIN categories c ON c.id = r.category_id WHERE r.is_archived = 0 ORDER BY r.created_at DESC, r.id DESC LIMIT 8')->fetchAll();
$byCat = $pdo->query('SELECT c.name, COUNT(r.id) AS n FROM categories c LEFT JOIN reports r ON r.category_id = c.id AND r.is_archived = 0 GROUP BY c.id, c.name ORDER BY n DESC, c.name LIMIT 8')->fetchAll();
$maxCat = max(1, ...array_map(fn($x) => (int)$x['n'], $byCat ?: [['n' => 1]]));
$done = (int)$s['total'] > 0 ? round($s['selesai'] / $s['total'] * 100) : 0;

admin_header('Ringkasan', 'index', $admin);
$cards = [
    ['Total laporan', $s['total'], 'fa-clipboard-list', ''],
    ['Belum ditangani', $s['belum'], 'fa-hourglass-start', 's-belum'],
    ['Dalam proses', $s['proses'], 'fa-gears', 's-proses'],
    ['Selesai', $s['selesai'], 'fa-circle-check', 's-selesai'],
];
?>
<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $num, $icon, $cls]): ?>
        <div class="col-6 col-xl-3">
            <div class="admin-stat <?= $cls ?>">
                <div class="ico"><i class="fa-solid <?= $icon ?>"></i></div>
                <div><div class="num"><?= (int)$num ?></div><div class="small text-muted"><?= e($label) ?></div></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="panel">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <h2 class="h6 mb-0 brand-font">Laporan terbaru</h2>
                <a href="reports.php" class="btn btn-sm btn-outline-primary">Lihat semua</a>
            </div>
            <?php if (!$recent): ?>
                <div class="empty-state"><i class="fa-solid fa-inbox d-block"></i>Belum ada laporan masuk.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Judul</th><th>Kab/Kota</th><th>Tanggal</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><a class="fw-semibold text-decoration-none" href="report.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a><div class="small text-muted"><?= e($r['category']) ?></div></td>
                            <td><?= e($r['regency']) ?></td>
                            <td class="text-nowrap"><?= e(id_date($r['created_at'])) ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel p-3 mb-3">
            <div class="d-flex justify-content-between small mb-1"><span>Tingkat penyelesaian</span><strong><?= $done ?>%</strong></div>
            <div class="progress" style="height:10px"><div class="progress-bar bg-success" style="width:<?= $done ?>%"></div></div>
            <div class="small text-muted mt-2"><?= $archived ?> laporan berada di arsip.</div>
        </div>
        <div class="panel p-3">
            <h2 class="h6 brand-font mb-3">Laporan per kategori</h2>
            <?php foreach ($byCat as $c): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small"><span><?= e($c['name']) ?></span><strong><?= (int)$c['n'] ?></strong></div>
                    <div class="progress" style="height:6px"><div class="progress-bar" style="width:<?= round($c['n'] / $maxCat * 100) ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php admin_footer(); ?>

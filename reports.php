<?php
require_once __DIR__ . '/_layout.php';
$admin = require_admin();
$pdo = db();

// Arsipkan / pulihkan laporan (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $do = (string)($_POST['do'] ?? '');
    if ($id > 0 && in_array($do, ['archive', 'unarchive'], true)) {
        if (set_archive($id, (int)$admin['id'], $do === 'archive')) {
            flash('ok', $do === 'archive' ? 'Laporan diarsipkan.' : 'Laporan dipulihkan dari arsip.');
        } else {
            flash('error', 'Perubahan gagal: status arsip laporan sudah berubah.');
        }
    }
    $back = preg_replace('/[^A-Za-z0-9%=&_.+\-]/', '', (string)($_POST['back'] ?? ''));
    header('Location: reports.php' . ($back !== '' ? '?' . $back : ''));
    exit;
}

[$where, $params] = report_filters($_GET, true);
$page = max(1, (int)($_GET['page'] ?? 1));
$per = PER_PAGE_ADMIN;
$off = ($page - 1) * $per;

$st = $pdo->prepare("SELECT COUNT(*) FROM reports r $where");
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $per));

$st = $pdo->prepare("SELECT r.id, r.title, r.regency, r.status, r.photo, r.created_at, c.name AS category FROM reports r JOIN categories c ON c.id = r.category_id $where ORDER BY r.created_at DESC, r.id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();

$filterKeys = ['q', 'status', 'category', 'regency', 'date_from', 'date_to', 'archived'];
$fq = array_filter(array_intersect_key($_GET, array_flip($filterKeys)), fn($v) => is_string($v) && $v !== '');
$val = fn($k) => e((string)($_GET[$k] ?? ''));
$showArchived = !empty($_GET['archived']);

admin_header('Semua laporan', 'reports', $admin);
?>
<form method="get" class="panel p-3 mb-3">
    <div class="row g-2">
        <div class="col-12 col-md-6 col-xl-3"><input type="search" name="q" class="form-control" placeholder="Cari judul, deskripsi, alamat" value="<?= $val('q') ?>" aria-label="Cari"></div>
        <div class="col-6 col-md-3 col-xl-2">
            <select name="status" class="form-select" aria-label="Status">
                <option value="">Semua status</option>
                <?php foreach (STATUSES as $k => $v): ?><option value="<?= e($k) ?>" <?= ($_GET['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <select name="category" class="form-select" aria-label="Kategori">
                <option value="">Semua kategori</option>
                <?php foreach (categories() as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (string)($_GET['category'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <select name="regency" class="form-select" aria-label="Kabupaten/kota">
                <option value="">Semua kabupaten/kota</option>
                <?php foreach (regencies() as $n => $_): ?><option value="<?= e($n) ?>" <?= ($_GET['regency'] ?? '') === $n ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-1"><input type="date" name="date_from" class="form-control" value="<?= $val('date_from') ?>" aria-label="Dari tanggal" title="Dari tanggal"></div>
        <div class="col-6 col-md-3 col-xl-1"><input type="date" name="date_to" class="form-control" value="<?= $val('date_to') ?>" aria-label="Sampai tanggal" title="Sampai tanggal"></div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
        <div class="form-check me-auto">
            <input class="form-check-input" type="checkbox" name="archived" value="1" id="archived" <?= $showArchived ? 'checked' : '' ?>>
            <label class="form-check-label" for="archived">Tampilkan arsip</label>
        </div>
        <a href="reports.php" class="btn btn-outline-secondary btn-sm">Atur ulang</a>
        <a href="export.php?<?= e(http_build_query($fq)) ?>" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-file-csv me-1"></i>Ekspor CSV</a>
        <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i>Terapkan</button>
    </div>
</form>

<div class="panel">
    <div class="px-3 py-2 border-bottom small text-muted"><?= $total ?> laporan <?= $showArchived ? '(arsip)' : '' ?></div>
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="fa-solid fa-inbox d-block"></i>Tidak ada laporan yang cocok dengan filter.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Foto</th><th>Judul</th><th>Kategori</th><th>Kab/Kota</th><th>Tanggal</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-muted"><?= (int)$r['id'] ?></td>
                    <td><?php if ($r['photo']): ?><img class="table-thumb" src="<?= e(UPLOAD_URL . $r['photo']) ?>" alt="" loading="lazy"><?php else: ?><div class="table-thumb"></div><?php endif; ?></td>
                    <td style="min-width:200px"><a class="fw-semibold text-decoration-none" href="report.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a></td>
                    <td><?= e($r['category']) ?></td>
                    <td><?= e($r['regency']) ?></td>
                    <td class="text-nowrap"><?= e(id_date($r['created_at'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="report.php?id=<?= (int)$r['id'] ?>">Detail</a>
                        <form method="post" class="d-inline" data-confirm="<?= $showArchived ? 'Pulihkan laporan ini dari arsip?' : 'Arsipkan laporan ini? Laporan akan disembunyikan dari halaman publik.' ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="do" value="<?= $showArchived ? 'unarchive' : 'archive' ?>">
                            <input type="hidden" name="back" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
                            <button class="btn btn-sm btn-outline-secondary" title="<?= $showArchived ? 'Pulihkan' : 'Arsipkan' ?>"><i class="fa-solid <?= $showArchived ? 'fa-box-open' : 'fa-box-archive' ?>"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="p-3 d-flex justify-content-center" aria-label="Halaman">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?<?= e(qs(['page' => $page - 1])) ?>">Sebelumnya</a></li>
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= e(qs(['page' => $i])) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="?<?= e(qs(['page' => $page + 1])) ?>">Berikutnya</a></li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>

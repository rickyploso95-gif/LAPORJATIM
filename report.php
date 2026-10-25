<?php
require_once __DIR__ . '/_layout.php';
$admin = require_admin();
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$st = $pdo->prepare('SELECT r.*, c.name AS category FROM reports r JOIN categories c ON c.id = r.category_id WHERE r.id = ?');
$st->execute([$id]);
$r = $st->fetch();

if (!$r) {
    http_response_code(404);
    admin_header('Laporan tidak ditemukan', 'reports', $admin);
    echo '<div class="empty-state panel"><i class="fa-solid fa-circle-question d-block"></i>Laporan tidak ditemukan. <a href="reports.php">Kembali ke daftar</a></div>';
    admin_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $note = trim((string)($_POST['note'] ?? ''));
    $len = mb_strlen($note);
    $adminId = (int)$admin['id'];

    if ($action === 'archive' || $action === 'unarchive') {
        set_archive($id, $adminId, $action === 'archive')
            ? flash('ok', $action === 'archive' ? 'Laporan diarsipkan.' : 'Laporan dipulihkan dari arsip.')
            : flash('error', 'Perubahan gagal: status arsip laporan sudah berubah.');
    } elseif ($len > 1000) {
        flash('error', 'Catatan maksimal 1000 karakter.');
    } elseif ($action === 'change_status') {
        $next = ['belum' => 'proses', 'proses' => 'selesai'][$r['status']] ?? null;
        if ($r['is_archived']) {
            flash('error', 'Laporan sedang diarsipkan. Pulihkan dulu untuk mengubah status.');
        } elseif (!$next) {
            flash('warn', 'Laporan ini sudah berstatus Selesai.');
        } elseif ($next === 'selesai' && $len < 5) {
            flash('error', 'Catatan penanganan wajib diisi (minimal 5 karakter) sebelum menandai laporan selesai.');
        } else {
            $pdo->beginTransaction();
            $u = $pdo->prepare('UPDATE reports SET status = ? WHERE id = ? AND status = ?');
            $u->execute([$next, $id, $r['status']]);
            if ($u->rowCount() === 1) {
                $pdo->prepare('INSERT INTO report_history (report_id, admin_id, old_status, new_status, note) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$id, $adminId, $r['status'], $next, $note !== '' ? $note : null]);
                $pdo->commit();
                flash('ok', 'Status diubah menjadi ' . STATUSES[$next] . '. Halaman publik langsung ikut diperbarui.');
            } else {
                $pdo->rollBack();
                flash('error', 'Status sudah diubah admin lain. Halaman dimuat ulang.');
            }
        }
    } elseif ($action === 'add_note') {
        if ($len < 3) {
            flash('error', 'Catatan minimal 3 karakter.');
        } else {
            $pdo->prepare('INSERT INTO report_history (report_id, admin_id, old_status, new_status, note) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, $adminId, $r['status'], $r['status'], $note]);
            flash('ok', 'Catatan penanganan disimpan.');
        }
    }
    header('Location: report.php?id=' . $id);
    exit;
}

$hs = $pdo->prepare('SELECT h.*, a.name AS admin_name FROM report_history h LEFT JOIN admins a ON a.id = h.admin_id WHERE h.report_id = ? ORDER BY h.created_at DESC, h.id DESC');
$hs->execute([$id]);
$history = $hs->fetchAll();
$nextLabel = ['belum' => 'Proses', 'proses' => 'Selesai'][$r['status']] ?? null;

admin_header('Laporan #' . $id, 'reports', $admin, true);
?>
<a href="reports.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="fa-solid fa-arrow-left me-1"></i>Semua laporan</a>
<?php if ($r['is_archived']): ?><div class="alert alert-secondary py-2"><i class="fa-solid fa-box-archive me-1"></i>Laporan ini diarsipkan dan tidak tampil di halaman publik.</div><?php endif; ?>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="panel p-3 p-md-4">
            <?php if ($r['photo']): ?>
                <a href="<?= e(UPLOAD_URL . $r['photo']) ?>" target="_blank" rel="noopener"><img class="detail-photo mb-3" src="<?= e(UPLOAD_URL . $r['photo']) ?>" alt="Foto bukti laporan"></a>
            <?php endif; ?>
            <div class="mb-2"><?= status_badge($r['status']) ?></div>
            <h2 class="h4 brand-font"><?= e($r['title']) ?></h2>
            <p style="white-space:pre-line"><?= e($r['description']) ?></p>
            <dl class="row small mb-3">
                <dt class="col-sm-4 text-muted">Kategori</dt><dd class="col-sm-8"><?= e($r['category']) ?></dd>
                <dt class="col-sm-4 text-muted">Alamat</dt><dd class="col-sm-8"><?= e($r['address']) ?></dd>
                <dt class="col-sm-4 text-muted">Kabupaten/kota</dt><dd class="col-sm-8"><?= e($r['regency']) ?></dd>
                <dt class="col-sm-4 text-muted">Koordinat</dt>
                <dd class="col-sm-8"><?= e($r['latitude']) ?>, <?= e($r['longitude']) ?>
                    <a class="ms-1" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e($r['latitude']) ?>&amp;mlon=<?= e($r['longitude']) ?>#map=17/<?= e($r['latitude']) ?>/<?= e($r['longitude']) ?>">buka di OpenStreetMap</a></dd>
                <dt class="col-sm-4 text-muted">Dilaporkan</dt><dd class="col-sm-8"><?= e(id_date($r['created_at'], true)) ?></dd>
                <dt class="col-sm-4 text-muted">Diperbarui</dt><dd class="col-sm-8"><?= e(id_date($r['updated_at'], true)) ?></dd>
            </dl>
            <div id="detail-map" data-lat="<?= e($r['latitude']) ?>" data-lng="<?= e($r['longitude']) ?>" data-status="<?= e($r['status']) ?>"></div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="panel p-3 p-md-4 mb-3">
            <h2 class="h6 brand-font mb-3">Penanganan</h2>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <?php foreach (STATUSES as $k => $_): ?>
                    <span class="<?= $k === $r['status'] ? '' : 'opacity-50' ?>"><?= status_badge($k) ?></span>
                    <?php if ($k !== 'selesai'): ?><i class="fa-solid fa-chevron-right small text-muted"></i><?php endif; ?>
                <?php endforeach; ?>
            </div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <label class="form-label" for="note">Catatan penanganan</label>
                <textarea class="form-control mb-2" id="note" name="note" rows="4" maxlength="1000" placeholder="Tindakan yang dilakukan, petugas, atau alasan perubahan status."></textarea>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($nextLabel && !$r['is_archived']): ?>
                        <button class="btn btn-primary" name="action" value="change_status"><i class="fa-solid fa-forward-step me-1"></i>Ubah status ke <?= e($nextLabel) ?></button>
                    <?php endif; ?>
                    <button class="btn btn-outline-primary" name="action" value="add_note"><i class="fa-solid fa-note-sticky me-1"></i>Simpan catatan saja</button>
                </div>
                <?php if ($nextLabel === 'Selesai'): ?><div class="form-text">Catatan wajib diisi saat menandai laporan selesai.</div><?php endif; ?>
                <?php if (!$nextLabel): ?><div class="form-text">Laporan sudah selesai. Anda masih bisa menambah catatan.</div><?php endif; ?>
            </form>
            <hr>
            <form method="post" data-confirm="<?= $r['is_archived'] ? 'Pulihkan laporan dari arsip?' : 'Arsipkan laporan ini? Laporan akan disembunyikan dari halaman publik.' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="btn btn-sm btn-outline-secondary" name="action" value="<?= $r['is_archived'] ? 'unarchive' : 'archive' ?>">
                    <i class="fa-solid <?= $r['is_archived'] ? 'fa-box-open' : 'fa-box-archive' ?> me-1"></i><?= $r['is_archived'] ? 'Pulihkan dari arsip' : 'Arsipkan laporan' ?>
                </button>
            </form>
        </div>

        <div class="panel p-3 p-md-4">
            <h2 class="h6 brand-font mb-3">Riwayat perubahan</h2>
            <ul class="timeline">
                <?php foreach ($history as $h): ?>
                    <li>
                        <div class="small fw-semibold">
                            <?php if ($h['old_status'] === null): ?>Laporan diterima
                            <?php elseif ($h['old_status'] !== $h['new_status']): ?>Status: <?= e(STATUSES[$h['old_status']]) ?> &rarr; <?= e(STATUSES[$h['new_status']]) ?>
                            <?php else: ?>Catatan<?php endif; ?>
                        </div>
                        <?php if ($h['note']): ?><div class="small" style="white-space:pre-line"><?= e($h['note']) ?></div><?php endif; ?>
                        <div class="small text-muted"><?= e(id_date($h['created_at'], true)) ?> oleh <?= e($h['admin_name'] ?? 'Sistem/pelapor') ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php admin_footer(true); ?>

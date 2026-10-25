<?php
require_once __DIR__ . '/_layout.php';
$admin = require_admin();
$rows = db()->query('SELECT r.id, r.title, r.status, r.latitude, r.longitude, r.regency, r.address, r.category_id, r.created_at, c.name AS category FROM reports r JOIN categories c ON c.id = r.category_id WHERE r.is_archived = 0 ORDER BY r.created_at DESC LIMIT 3000')->fetchAll();
$data = array_map(fn($r) => [
    'id' => (int)$r['id'], 'title' => $r['title'], 'status' => $r['status'], 'lat' => (float)$r['latitude'], 'lng' => (float)$r['longitude'],
    'regency' => $r['regency'], 'address' => $r['address'], 'category' => $r['category'], 'category_id' => (int)$r['category_id'], 'date' => id_date($r['created_at']),
], $rows);

admin_header('Peta laporan', 'map', $admin, true);
?>
<div class="panel p-3 mb-3">
    <div class="row g-2 align-items-center">
        <div class="col-6 col-md-3">
            <select id="m-status" class="form-select form-select-sm" aria-label="Status">
                <option value="">Semua status</option>
                <?php foreach (STATUSES as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select id="m-category" class="form-select form-select-sm" aria-label="Kategori">
                <option value="">Semua kategori</option>
                <?php foreach (categories() as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <select id="m-regency" class="form-select form-select-sm" aria-label="Kabupaten/kota">
                <option value="">Semua kabupaten/kota</option>
                <?php foreach (regencies() as $n => $_): ?><option value="<?= e($n) ?>"><?= e($n) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 small text-muted text-md-end" id="m-count"></div>
    </div>
</div>
<div class="map-wrap"><div id="admin-map"></div>
    <div class="legend"><span><i class="dot dot-belum"></i>Belum ditangani</span><span><i class="dot dot-proses"></i>Proses</span><span><i class="dot dot-selesai"></i>Selesai</span></div>
</div>
<script type="application/json" id="map-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php admin_footer(true); ?>

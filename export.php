<?php
// Ekspor CSV mengikuti filter yang sama dengan daftar laporan. Hanya untuk admin yang login.
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();
[$where, $params] = report_filters($_GET, true);

$st = db()->prepare("SELECT r.id, r.title, r.description, c.name AS category, r.address, r.regency, r.latitude, r.longitude, r.status, r.is_archived, r.created_at, r.updated_at, r.photo FROM reports r JOIN categories c ON c.id = r.category_id $where ORDER BY r.created_at DESC, r.id DESC");
$st->execute($params);

// Cegah CSV injection: sel yang diawali = + - @ diberi tanda petik di depannya.
function csv_safe($v): string {
    $v = (string)$v;
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="laporjatim-' . date('Ymd-His') . '.csv"');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
$put = fn(array $row) => fputcsv($out, $row, ';', '"', '\\');
$put(['ID', 'Judul', 'Deskripsi', 'Kategori', 'Alamat', 'Kabupaten/Kota', 'Latitude', 'Longitude', 'Status', 'Diarsipkan', 'Tanggal Lapor', 'Terakhir Diperbarui', 'URL Foto']);
while ($r = $st->fetch()) {
    $put([
        $r['id'], csv_safe($r['title']), csv_safe($r['description']), csv_safe($r['category']), csv_safe($r['address']), $r['regency'],
        $r['latitude'], $r['longitude'], STATUSES[$r['status']], $r['is_archived'] ? 'Ya' : 'Tidak', $r['created_at'], $r['updated_at'],
        $r['photo'] ? (isset($_SERVER['HTTP_HOST']) ? (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . UPLOAD_URL . $r['photo'] : '',
    ]);
}
fclose($out);

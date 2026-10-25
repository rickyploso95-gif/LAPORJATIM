<?php
// Menerima laporan baru dari masyarakat (tanpa login). Dilindungi CSRF, honeypot, dan pembatasan per sesi.
require_once __DIR__ . '/../includes/auth.php';
send_security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Metode tidak diizinkan.'], 405);
if (!csrf_valid()) json_out(['ok' => false, 'message' => 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.'], 419);
if (!empty($_POST['website'])) json_out(['ok' => true, 'message' => 'Laporan diterima.']); // honeypot untuk bot

start_session();
$now = time();
$_SESSION['submits'] = array_values(array_filter($_SESSION['submits'] ?? [], fn($t) => $now - $t < 600));
if (count($_SESSION['submits']) >= 5) {
    json_out(['ok' => false, 'message' => 'Terlalu banyak laporan dalam waktu singkat. Coba lagi dalam beberapa menit.'], 429);
}

$title   = trim((string)($_POST['title'] ?? ''));
$desc    = trim((string)($_POST['description'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$regency = (string)($_POST['regency'] ?? '');
$catId   = ctype_digit((string)($_POST['category_id'] ?? '')) ? (int)$_POST['category_id'] : 0;
$latRaw  = $_POST['latitude'] ?? '';
$lngRaw  = $_POST['longitude'] ?? '';

$errors = [];
$pdo = db();

if (mb_strlen($title) < 5 || mb_strlen($title) > 150) $errors['title'] = 'Judul harus 5–150 karakter.';
if (mb_strlen($desc) < 20 || mb_strlen($desc) > 2000) $errors['description'] = 'Deskripsi harus 20–2000 karakter.';
if (mb_strlen($address) < 5 || mb_strlen($address) > 255) $errors['address'] = 'Alamat harus 5–255 karakter.';
if (!is_valid_regency($regency)) $errors['regency'] = 'Pilih kabupaten/kota yang valid.';

$st = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
$st->execute([$catId]);
if (!$st->fetchColumn()) $errors['category_id'] = 'Pilih kategori yang valid.';

if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
    $errors['coords'] = 'Tandai lokasi masalah pada peta.';
} else {
    $lat = round((float)$latRaw, 7);
    $lng = round((float)$lngRaw, 7);
    if ($lat < LAT_MIN || $lat > LAT_MAX || $lng < LNG_MIN || $lng > LNG_MAX) {
        $errors['coords'] = 'Lokasi harus berada di wilayah Jawa Timur.';
    }
}

if ($errors) json_out(['ok' => false, 'message' => 'Periksa kembali isian formulir.', 'errors' => $errors], 422);

[$photo, $photoErr] = save_photo($_FILES['photo'] ?? null, PHOTO_REQUIRED);
if ($photoErr) json_out(['ok' => false, 'message' => 'Periksa kembali isian formulir.', 'errors' => ['photo' => $photoErr]], 422);

try {
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO reports (title, description, category_id, address, regency, latitude, longitude, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$title, $desc, $catId, $address, $regency, $lat, $lng, $photo]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_history (report_id, admin_id, old_status, new_status, note) VALUES (?, NULL, NULL, 'belum', 'Laporan diterima dari masyarakat.')")
        ->execute([$id]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($photo) @unlink(UPLOAD_DIR . $photo);
    error_log('api/submit: ' . $e->getMessage());
    json_out(['ok' => false, 'message' => 'Laporan gagal disimpan. Coba lagi nanti.'], 500);
}

$_SESSION['submits'][] = $now;
json_out(['ok' => true, 'id' => $id, 'message' => "Laporan #$id berhasil dikirim dengan status Belum Ditangani. Admin akan memperbarui statusnya."]);

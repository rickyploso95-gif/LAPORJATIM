<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/regions.php';

const STATUSES = ['belum' => 'Belum Ditangani', 'proses' => 'Proses', 'selesai' => 'Selesai'];

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function send_security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function json_out(array $data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function id_date(?string $ts, bool $time = false): string {
    if (!$ts) return '-';
    $t = strtotime($ts);
    $m = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('j', $t) . ' ' . $m[(int)date('n', $t) - 1] . ' ' . date('Y', $t) . ($time ? ', ' . date('H:i', $t) : '');
}

function status_badge(string $s): string {
    return '<span class="badge status-badge badge-' . e($s) . '">' . e(STATUSES[$s] ?? $s) . '</span>';
}

function categories(): array {
    return db()->query("SELECT id, name FROM categories ORDER BY name = 'Lainnya', name")->fetchAll();
}

function valid_date($d): bool {
    $x = DateTime::createFromFormat('Y-m-d', (string)$d);
    return $x && $x->format('Y-m-d') === $d;
}

/** Bangun klausa WHERE dari parameter filter (dipakai API publik, tabel admin, peta admin, ekspor CSV). */
function report_filters(array $src, bool $admin): array {
    $w = [];
    $p = [];
    if ($admin) {
        $w[] = 'r.is_archived = ?';
        $p[] = !empty($src['archived']) ? 1 : 0;
    } else {
        $w[] = 'r.is_archived = 0';
    }
    if (!empty($src['status']) && isset(STATUSES[$src['status']])) {
        $w[] = 'r.status = ?';
        $p[] = $src['status'];
    }
    if (!empty($src['category']) && ctype_digit((string)$src['category'])) {
        $w[] = 'r.category_id = ?';
        $p[] = (int)$src['category'];
    }
    if (!empty($src['regency']) && is_valid_regency($src['regency'])) {
        $w[] = 'r.regency = ?';
        $p[] = $src['regency'];
    }
    $q = trim((string)($src['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
        $w[] = '(r.title LIKE ? OR r.description LIKE ? OR r.address LIKE ?)';
        array_push($p, $like, $like, $like);
    }
    if ($admin) {
        if (!empty($src['date_from']) && valid_date($src['date_from'])) {
            $w[] = 'r.created_at >= ?';
            $p[] = $src['date_from'] . ' 00:00:00';
        }
        if (!empty($src['date_to']) && valid_date($src['date_to'])) {
            $w[] = 'r.created_at <= ?';
            $p[] = $src['date_to'] . ' 23:59:59';
        }
    }
    return [' WHERE ' . implode(' AND ', $w), $p];
}

/** Validasi + simpan foto. Mengembalikan [namaFile|null, pesanError|null]. */
function save_photo(?array $file, bool $required): array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $required ? [null, 'Foto bukti wajib diunggah.'] : [null, null];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) return [null, 'Ukuran foto maksimal 3 MB.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return [null, 'Foto gagal diunggah. Coba lagi.'];
    if ($file['size'] > MAX_UPLOAD_BYTES) return [null, 'Ukuran foto maksimal 3 MB.'];
    if (!is_uploaded_file($file['tmp_name'])) return [null, 'Berkas tidak valid.'];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || !@getimagesize($file['tmp_name'])) return [null, 'Format foto harus JPG, PNG, atau WebP.'];

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) return [null, 'Foto gagal disimpan di server.'];
    return [$name, null];
}

function password_error(string $p): ?string {
    if (mb_strlen($p) < 10) return 'Password minimal 10 karakter.';
    if (mb_strlen($p) > 200) return 'Password terlalu panjang.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Password harus mengandung huruf dan angka.';
    return null;
}

/** Arsipkan / pulihkan laporan dan catat di riwayat. */
function set_archive(int $id, int $adminId, bool $archive): bool {
    $pdo = db();
    $pdo->beginTransaction();
    $u = $pdo->prepare('UPDATE reports SET is_archived = ? WHERE id = ? AND is_archived = ?');
    $u->execute([$archive ? 1 : 0, $id, $archive ? 0 : 1]);
    if ($u->rowCount() !== 1) {
        $pdo->rollBack();
        return false;
    }
    $pdo->prepare('INSERT INTO report_history (report_id, admin_id, old_status, new_status, note) SELECT id, ?, status, status, ? FROM reports WHERE id = ?')
        ->execute([$adminId, $archive ? 'Laporan diarsipkan.' : 'Laporan dipulihkan dari arsip.', $id]);
    $pdo->commit();
    return true;
}

/** Query string saat ini dengan override tertentu (untuk paginasi). */
function qs(array $override = []): string {
    return http_build_query(array_merge($_GET, $override));
}

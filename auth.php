<?php
require_once __DIR__ . '/helpers.php';

const SESSION_IDLE_SECONDS = 7200; // 2 jam tanpa aktivitas = logout otomatis

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('laporjatim_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool {
    start_session();
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function require_csrf(): void {
    if (!csrf_valid()) {
        http_response_code(419);
        exit('Sesi tidak valid atau token CSRF salah. Muat ulang halaman lalu coba lagi.');
    }
}

/* ---------- Flash message ---------- */
function flash(string $type, string $msg): void {
    start_session();
    $_SESSION['flash'][] = [$type, $msg];
}

function flashes(): array {
    start_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Autentikasi admin ---------- */
function do_logout(): void {
    start_session();
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

/** Admin yang sedang login (dicek ulang ke database tiap request, jadi akun nonaktif langsung kehilangan akses). */
function current_admin(): ?array {
    static $admin = false;
    if ($admin !== false) return $admin;
    start_session();
    $admin = null;
    if (!empty($_SESSION['admin_id'])) {
        if (time() - (int)($_SESSION['last'] ?? 0) > SESSION_IDLE_SECONDS) {
            do_logout();
        } else {
            $st = db()->prepare('SELECT id, name, email, role FROM admins WHERE id = ? AND is_active = 1');
            $st->execute([(int)$_SESSION['admin_id']]);
            $row = $st->fetch();
            if ($row) {
                $_SESSION['last'] = time();
                $admin = $row;
            } else {
                do_logout();
            }
        }
    }
    return $admin;
}

/** Wajib login. Untuk halaman khusus superadmin kirim 'superadmin'. */
function require_admin(?string $role = null): array {
    $a = current_admin();
    if (!$a) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
    if ($role === 'superadmin' && $a['role'] !== 'superadmin') {
        http_response_code(403);
        exit('Akses ditolak: halaman ini hanya untuk superadmin.');
    }
    return $a;
}

/** Mengembalikan pesan error, atau null jika login berhasil. */
function attempt_login(string $email, string $pass): ?string {
    $pdo = db();
    $generic = 'Email atau password salah.';
    $st = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
    $st->execute([mb_strtolower(trim($email))]);
    $a = $st->fetch();

    if (!$a) {
        password_verify($pass, password_hash('dummy-timing', PASSWORD_DEFAULT)); // samakan waktu respons
        return $generic;
    }
    if (!$a['is_active']) return $generic;
    if ($a['locked_until'] && strtotime($a['locked_until']) > time()) {
        return 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . LOGIN_LOCK_MINUTES . ' menit.';
    }
    if (!password_verify($pass, $a['password_hash'])) {
        $n = (int)$a['failed_attempts'] + 1;
        if ($n >= LOGIN_MAX_ATTEMPTS) {
            $pdo->prepare('UPDATE admins SET failed_attempts = 0, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
                ->execute([LOGIN_LOCK_MINUTES, $a['id']]);
        } else {
            $pdo->prepare('UPDATE admins SET failed_attempts = ? WHERE id = ?')->execute([$n, $a['id']]);
        }
        return $generic;
    }

    $pdo->prepare('UPDATE admins SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$a['id']]);
    if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $a['id']]);
    }
    start_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$a['id'];
    $_SESSION['last'] = time();
    return null;
}

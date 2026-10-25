<?php
// Manajemen akun admin: hanya superadmin.
require_once __DIR__ . '/_layout.php';
$admin = require_admin('superadmin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $role = (string)($_POST['role'] ?? 'admin');
        $pass = (string)($_POST['password'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            flash('error', 'Nama harus 2–100 karakter.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            flash('error', 'Format email tidak valid.');
        } elseif (!in_array($role, ['admin', 'superadmin'], true)) {
            flash('error', 'Peran tidak valid.');
        } elseif ($err = password_error($pass)) {
            flash('error', $err);
        } else {
            try {
                $pdo->prepare('INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
                flash('ok', "Akun $email berhasil dibuat.");
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000' ? 'Email sudah terdaftar.' : 'Akun gagal dibuat.');
            }
        }
    } elseif ($act === 'toggle') {
        $tid = (int)($_POST['id'] ?? 0);
        if ($tid === (int)$admin['id']) {
            flash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        } else {
            $pdo->prepare('UPDATE admins SET is_active = 1 - is_active WHERE id = ?')->execute([$tid]);
            flash('ok', 'Status akun diperbarui.');
        }
    } elseif ($act === 'reset') {
        $tid = (int)($_POST['id'] ?? 0);
        $pass = (string)($_POST['password'] ?? '');
        if ($err = password_error($pass)) {
            flash('error', $err);
        } else {
            $pdo->prepare('UPDATE admins SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $tid]);
            flash('ok', 'Password berhasil direset.');
        }
    }
    header('Location: admins.php');
    exit;
}

$admins = $pdo->query('SELECT id, name, email, role, is_active, last_login_at, created_at FROM admins ORDER BY created_at')->fetchAll();
admin_header('Akun admin', 'admins', $admin);
?>
<div class="row g-3">
    <div class="col-xl-4">
        <form method="post" class="panel p-3 p-md-4">
            <h2 class="h6 brand-font mb-3">Tambah admin</h2>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="mb-2"><label class="form-label" for="a-name">Nama</label><input class="form-control" id="a-name" name="name" required maxlength="100"></div>
            <div class="mb-2"><label class="form-label" for="a-email">Email</label><input type="email" class="form-control" id="a-email" name="email" required maxlength="150"></div>
            <div class="mb-2"><label class="form-label" for="a-role">Peran</label>
                <select class="form-select" id="a-role" name="role"><option value="admin">Admin (kelola laporan)</option><option value="superadmin">Superadmin (kelola laporan dan akun)</option></select>
            </div>
            <div class="mb-3"><label class="form-label" for="a-pass">Password</label><input type="password" class="form-control" id="a-pass" name="password" required minlength="10" autocomplete="new-password"><div class="form-text">Minimal 10 karakter, mengandung huruf dan angka.</div></div>
            <button class="btn btn-primary w-100">Buat akun</button>
        </form>
    </div>
    <div class="col-xl-8">
        <div class="panel">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Admin</th><th>Peran</th><th>Login terakhir</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($admins as $a): $self = (int)$a['id'] === (int)$admin['id']; ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e($a['name']) ?><?= $self ? ' (Anda)' : '' ?></div><div class="small text-muted"><?= e($a['email']) ?></div></td>
                            <td><?= $a['role'] === 'superadmin' ? 'Superadmin' : 'Admin' ?></td>
                            <td class="small text-nowrap"><?= e(id_date($a['last_login_at'], true)) ?></td>
                            <td><?= $a['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                            <td class="text-end">
                                <?php if (!$self): ?>
                                <form method="post" class="d-inline" data-confirm="<?= $a['is_active'] ? 'Nonaktifkan akun ini?' : 'Aktifkan akun ini?' ?>">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><?= $a['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                </form>
                                <?php endif; ?>
                                <form method="post" class="d-inline-flex gap-1 mt-1" autocomplete="off">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <input type="password" name="password" class="form-control form-control-sm" style="width:130px" placeholder="Password baru" minlength="10" required autocomplete="new-password" aria-label="Password baru untuk <?= e($a['name']) ?>">
                                    <button class="btn btn-sm btn-outline-primary" title="Reset password"><i class="fa-solid fa-key"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php admin_footer(); ?>
